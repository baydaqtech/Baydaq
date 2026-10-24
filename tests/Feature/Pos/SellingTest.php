<?php

namespace Tests\Feature\Pos;

use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Subscription;
use App\Modules\Pos\Actions\ManageShift;
use App\Modules\Pos\Actions\PayOrder;
use App\Modules\Pos\Actions\PlaceOrder;
use App\Modules\Pos\Actions\SaveCashier;
use App\Modules\Pos\Actions\SaveProduct;
use App\Modules\Pos\Actions\VoidOrder;
use App\Modules\Pos\Enums\OrderStatus;
use App\Modules\Pos\Events\OrderPaid;
use App\Modules\Pos\Models\Cashier;
use App\Modules\Pos\Models\Category;
use App\Modules\Pos\Models\ModifierGroup;
use App\Modules\Pos\Models\Order;
use App\Modules\Pos\Models\Product;
use App\Modules\Pos\Models\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsSuite;
use Tests\Concerns\FakesZatca;
use Tests\TestCase;

class SellingTest extends TestCase
{
    use BuildsSuite, FakesZatca, RefreshDatabase;

    private Product $latte;

    private Product $cake;

    private Register $register;

    private Cashier $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSuite();
        $this->travelTo('2026-11-01 10:00:00');

        $category = (new Category(['company_id' => $this->company->id, 'name_ar' => 'مشروبات', 'name_en' => 'Drinks']))->forceFill(['client_id' => $this->client->id]);
        $category->save();
        $size = (new ModifierGroup(['company_id' => $this->company->id, 'name_ar' => 'الحجم', 'name_en' => 'Size', 'min' => 0, 'max' => 1]))->forceFill(['client_id' => $this->client->id]);
        $size->save();
        $size->modifiers()->create(['name_ar' => 'كبير', 'name_en' => 'Large', 'price' => '3.00']);

        $this->latte = app(SaveProduct::class)->handle(new Product, ['company_id' => $this->company->id, 'category_id' => $category->id, 'name_ar' => 'لاتيه', 'name_en' => 'Latte', 'price' => '17.25', 'calories' => 180], [$size->id]);
        $this->cake = app(SaveProduct::class)->handle(new Product, ['company_id' => $this->company->id, 'category_id' => $category->id, 'name_ar' => 'كيك', 'name_en' => 'Cake', 'price' => '23.00'], [], [
            ['branch_id' => $this->branch('JED')->id, 'price' => '25.00'],
            ['branch_id' => $this->branch('RUH')->id, 'is_available' => false],
        ]);

        $this->register = (new Register(['name' => 'Till 1']))->forceFill(['client_id' => $this->client->id, 'company_id' => $this->company->id, 'branch_id' => $this->headOffice->id, 'menu_token' => Str::random(32)]);
        $this->register->save();
        $this->cashier = app(SaveCashier::class)->handle(new Cashier, ['company_id' => $this->company->id, 'name' => 'Sara'], '1234');
    }

    public function test_an_order_prices_lines_with_modifiers_and_splits_vat(): void
    {
        $large = $this->latte->modifierGroups->first()->modifiers->first();

        $order = app(PlaceOrder::class)->handle(new Order, $this->headOffice->id, $this->register, null, ['type' => 'dine_in', 'table_no' => '4'], [
            ['product_id' => $this->latte->id, 'quantity' => 2, 'modifier_ids' => [$large->id]],
            ['product_id' => $this->cake->id, 'quantity' => 1],
        ]);

        $this->assertSame(1, $order->number);
        $this->assertSame('20.25', (string) $order->lines[0]->unit_price);   // 17.25 + 3.00 large
        $this->assertSame('40.50', (string) $order->lines[0]->total);
        $this->assertSame('35.22', (string) $order->lines[0]->net);          // 40.50 / 1.15
        $this->assertSame('5.28', (string) $order->lines[0]->vat);
        $this->assertStringContainsString('كبير', $order->lines[0]->name); // tests run in Arabic
        $this->assertSame('63.50', (string) $order->total);
        $this->assertSame('8.28', (string) $order->vat);
    }

    public function test_branch_overrides_change_price_or_block_the_product(): void
    {
        $jed = app(PlaceOrder::class)->handle(new Order, $this->branch('JED')->id, null, null, [], [['product_id' => $this->cake->id, 'quantity' => 1]]);
        $this->assertSame('25.00', (string) $jed->total);

        $this->expectException(ValidationException::class);
        app(PlaceOrder::class)->handle(new Order, $this->branch('RUH')->id, null, null, [], [['product_id' => $this->cake->id, 'quantity' => 1]]);
    }

    public function test_paying_issues_a_simplified_invoice_when_the_tenant_has_invoicing(): void
    {
        Event::fake([OrderPaid::class]);
        Subscription::factory()->module(Module::Invoicing)->create(['client_id' => $this->client->id, 'quantity' => 1]);
        Company::query()->whereKey($this->company->id)->update(['vat_number' => '300000000000003', 'cr_number' => '1010101010', 'city' => 'Riyadh', 'postal_code' => '12211', 'building_number' => '1234', 'street' => 'X', 'district' => 'Y']);
        $unit = $this->readyUnit($this->company->id, $this->client->id)['unit'];
        $this->register->forceFill(['einvoice_unit_id' => $unit->id])->save();
        $this->fakeZatcaAccepting();

        $order = app(PlaceOrder::class)->handle(new Order, $this->headOffice->id, $this->register, null, [], [['product_id' => $this->latte->id, 'quantity' => 2]]);

        try {
            app(PayOrder::class)->handle($order, [['method' => 'cash', 'amount' => '30.00']]);
            $this->fail('Short payment accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payments', $e->errors());
        }

        $order = app(PayOrder::class)->handle($order, [['method' => 'cash', 'amount' => '20.00'], ['method' => 'mada', 'amount' => '14.50']], $this->owner);

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertCount(2, $order->payments);
        $this->assertNotNull($order->invoice_id);
        $invoice = Invoice::query()->findOrFail($order->invoice_id);
        $this->assertSame('reported', $invoice->status->value);
        $this->assertSame('34.50', (string) $invoice->total);  // same gross as the order
        $this->assertSame('4.50', (string) $invoice->vat);
        Event::assertDispatched(OrderPaid::class);
    }

    public function test_an_order_discount_is_spread_over_the_invoice_lines_so_totals_match(): void
    {
        Subscription::factory()->module(Module::Invoicing)->create(['client_id' => $this->client->id, 'quantity' => 1]);
        Company::query()->whereKey($this->company->id)->update(['vat_number' => '300000000000003', 'cr_number' => '1010101010', 'city' => 'Riyadh', 'postal_code' => '12211', 'building_number' => '1234', 'street' => 'X', 'district' => 'Y']);
        $this->register->forceFill(['einvoice_unit_id' => $this->readyUnit($this->company->id, $this->client->id)['unit']->id])->save();
        $this->fakeZatcaAccepting();

        $order = app(PlaceOrder::class)->handle(new Order, $this->headOffice->id, $this->register, null, ['discount' => '4.50'], [
            ['product_id' => $this->latte->id, 'quantity' => 2],
            ['product_id' => $this->cake->id, 'quantity' => 1],
        ]);

        // 57.50 - 4.50: the customer pays the menu prices less the discount, VAT inside.
        $this->assertSame('53.00', (string) $order->total);
        $this->assertSame('46.08', (string) $order->subtotal);  // 31.80 / 1.15 + 21.20 / 1.15
        $this->assertSame('6.92', (string) $order->vat);

        $order = app(PayOrder::class)->handle($order, [['method' => 'cash', 'amount' => '53.00']]);
        $invoice = Invoice::query()->findOrFail($order->invoice_id);
        // ZATCA rounds VAT per line (4.15 + 2.76 → 52.99); the halala goes to payable rounding.
        $this->assertSame('52.99', (string) $invoice->total);
        $this->assertSame('0.01', (string) $invoice->rounding);
        $this->assertSame('53.00', $invoice->payableAmount());
        $xml = $invoice->submissions()->latest('id')->value('xml');
        $this->assertStringContainsString('<cbc:PayableRoundingAmount currencyID="SAR">0.01</cbc:PayableRoundingAmount>', $xml);
        $this->assertStringContainsString('<cbc:PayableAmount currencyID="SAR">53.00</cbc:PayableAmount>', $xml);

        try {
            app(PlaceOrder::class)->handle(new Order, $this->headOffice->id, $this->register, null, ['discount' => '100'], [['product_id' => $this->cake->id, 'quantity' => 1]]);
            $this->fail('A discount above the total was accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('discount', $e->errors());
        }
    }

    public function test_without_invoicing_the_sale_still_closes_and_a_paid_order_cannot_be_voided(): void
    {
        $order = app(PlaceOrder::class)->handle(new Order, $this->headOffice->id, $this->register, null, [], [['product_id' => $this->latte->id, 'quantity' => 1]]);
        $order = app(PayOrder::class)->handle($order, [['method' => 'cash', 'amount' => '17.25']]);

        $this->assertNull($order->invoice_id);

        $this->expectException(ValidationException::class);
        app(VoidOrder::class)->handle($order, 'oops', $this->owner);
    }

    public function test_a_shift_opens_with_a_pin_and_closes_with_the_cash_difference(): void
    {
        try {
            app(ManageShift::class)->open($this->register, $this->cashier, '0000', '200');
            $this->fail('Wrong PIN accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('pin', $e->errors());
        }

        $shift = app(ManageShift::class)->open($this->register, $this->cashier, '1234', '200.00');

        $order = app(PlaceOrder::class)->handle(new Order, $this->headOffice->id, $this->register, $shift, [], [['product_id' => $this->latte->id, 'quantity' => 2]]);
        app(PayOrder::class)->handle($order, [['method' => 'cash', 'amount' => '20.00'], ['method' => 'card', 'amount' => '14.50']]);
        $open = app(PlaceOrder::class)->handle(new Order, $this->headOffice->id, $this->register, $shift, [], [['product_id' => $this->latte->id, 'quantity' => 1]]);
        app(VoidOrder::class)->handle($open, 'customer left', $this->owner);

        $shift = app(ManageShift::class)->close($shift, '215.00');

        $this->assertSame('220.00', (string) $shift->expected_cash);  // 200 float + 20 cash
        $this->assertSame('-5.00', (string) $shift->difference);
        $this->assertSame(1, $shift->orders_count);
        $this->assertSame('34.50', (string) $shift->sales_total);
        $this->assertNull($this->register->fresh()->openShift()->first());
    }
}
