<?php

namespace Tests\Feature\Pos;

use App\Modules\Invoicing\Enums\InvoiceKind;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Customer;
use App\Modules\Platform\Models\Subscription;
use App\Modules\Pos\Actions\ManageShift;
use App\Modules\Pos\Actions\PayOrder;
use App\Modules\Pos\Actions\PlaceOrder;
use App\Modules\Pos\Actions\RefundOrder;
use App\Modules\Pos\Actions\SaveCashier;
use App\Modules\Pos\Actions\SaveProduct;
use App\Modules\Pos\Enums\OrderStatus;
use App\Modules\Pos\Enums\RefundMethod;
use App\Modules\Pos\Models\Cashier;
use App\Modules\Pos\Models\Category;
use App\Modules\Pos\Models\Order;
use App\Modules\Pos\Models\Product;
use App\Modules\Pos\Models\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\AssertsLedger;
use Tests\Concerns\BuildsSuite;
use Tests\Concerns\FakesZatca;
use Tests\TestCase;

/**
 * Refunds of paid orders: what comes back, at what it was paid, where the money goes, and what
 * follows in the till, the wallet, the books and ZATCA.
 */
class RefundTest extends TestCase
{
    use AssertsLedger, BuildsSuite, FakesZatca, RefreshDatabase;

    private Product $latte;

    private Register $register;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-11-01 10:00:00');
        $this->asTenant();

        $category = (new Category(['company_id' => $this->company->id, 'name_ar' => 'مشروبات', 'name_en' => 'Drinks']))->forceFill(['client_id' => $this->client->id]);
        $category->save();
        $this->latte = app(SaveProduct::class)->handle(new Product, ['company_id' => $this->company->id, 'category_id' => $category->id, 'name_ar' => 'لاتيه', 'name_en' => 'Latte', 'price' => '20.00']);
        $this->register = (new Register(['name' => 'Till 1']))->forceFill(['client_id' => $this->client->id, 'company_id' => $this->company->id, 'branch_id' => $this->headOffice->id, 'menu_token' => Str::random(32)]);
        $this->register->save();
    }

    /** Three lattes (60.00) with 6.00 off, paid by card. */
    private function paidOrder(array $data = [], array $payments = [['method' => 'card', 'amount' => '54.00']]): Order
    {
        $order = app(PlaceOrder::class)->handle(new Order, $this->headOffice->id, $this->register, null, ['discount' => '6.00', ...$data], [['product_id' => $this->latte->id, 'quantity' => 3]]);

        return app(PayOrder::class)->handle($order, $payments, $this->owner);
    }

    public function test_items_come_back_at_what_was_paid_until_none_are_left(): void
    {
        $order = $this->paidOrder();
        $line = $order->lines->first();

        $refund = app(RefundOrder::class)->handle($order, [$line->id => 2], RefundMethod::Card, 'Cold', $this->owner);

        $this->assertSame('36.00', (string) $refund->total);   // 2 of 3, each 18.00 after the discount
        $this->assertSame('31.30', (string) $refund->subtotal); // VAT 4.70 inside
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertPostedBalanced('pos.refund', $refund->id);
        $this->assertSame('18.00', $this->ledgerBalance($this->company->id, 'card_clearing')); // 54 in, 36 back

        try {
            app(RefundOrder::class)->handle($order, [$line->id => 2], RefundMethod::Card, null, $this->owner);
            $this->fail('Refunded more than was sold.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lines', $exception->errors());
        }

        app(RefundOrder::class)->handle($order, [$line->id => 1], RefundMethod::Card, null, $this->owner);
        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
        $this->assertSame('54.00', (string) $order->fresh()->refunded);
    }

    public function test_cash_comes_out_of_the_open_till(): void
    {
        $cashier = app(SaveCashier::class)->handle(new Cashier, ['company_id' => $this->company->id, 'name' => 'Sara'], '1234');
        $order = $this->paidOrder(payments: [['method' => 'cash', 'amount' => '54.00']]);

        try {
            app(RefundOrder::class)->handle($order, [$order->lines->first()->id => 1], RefundMethod::Cash, null, $this->owner);
            $this->fail('Cash refunded with no till open.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('method', $exception->errors());
        }

        $shift = app(ManageShift::class)->open($this->register, $cashier, '1234', '100.00');
        app(RefundOrder::class)->handle($order, [$order->lines->first()->id => 1], RefundMethod::Cash, null, $this->owner);

        $this->assertSame('82.00', (string) app(ManageShift::class)->close($shift, '82.00')->expected_cash); // 100 float − 18 back
    }

    public function test_a_wallet_refund_credits_the_customer_and_takes_back_the_loyalty_share(): void
    {
        Company::query()->whereKey($this->company->id)->update(['loyalty_percent' => 10]);
        $order = $this->paidOrder(['customer_name' => 'Noura', 'customer_phone' => '0501234567']);
        $customer = Customer::query()->sole();
        $this->assertSame('5.40', (string) $customer->wallet_balance); // 10 % of 54.00

        app(RefundOrder::class)->handle($order, [$order->lines->first()->id => 1], RefundMethod::Wallet, null, $this->owner);

        $this->assertSame('21.60', (string) $customer->fresh()->wallet_balance); // + 18.00 back − 1.80 loyalty
    }

    public function test_a_refund_of_an_invoiced_sale_raises_a_credit_note(): void
    {
        Subscription::factory()->module(Module::Invoicing)->create(['client_id' => $this->client->id, 'quantity' => 1]);
        Company::query()->whereKey($this->company->id)->update(['vat_number' => '300000000000003', 'cr_number' => '1010101010', 'city' => 'Riyadh', 'postal_code' => '12211', 'building_number' => '1234', 'street' => 'X', 'district' => 'Y']);
        $this->register->forceFill(['einvoice_unit_id' => $this->readyUnit($this->company->id, $this->client->id)['unit']->id])->save();
        $this->fakeZatcaAccepting();
        $order = $this->paidOrder();

        $refund = app(RefundOrder::class)->handle($order, [$order->lines->first()->id => 1], RefundMethod::Card, 'Spilt', $this->owner);

        $note = Invoice::query()->findOrFail($refund->fresh()->invoice_id);
        $this->assertSame(InvoiceKind::CreditNote, $note->kind);
        $this->assertSame($order->invoice_id, $note->related_invoice_id);
        $this->assertSame('reported', $note->status->value);
        $this->assertSame('18.00', (string) $note->total);
    }

    public function test_refunding_needs_the_refund_ability(): void
    {
        $order = $this->paidOrder();
        $seller = $this->memberWith(['pos.orders.view', 'pos.orders.sell'], ScopeLevel::Branch, $this->headOffice);
        Subscription::factory()->module(Module::Pos)->create(['client_id' => $this->client->id]);

        $this->actingAs($seller)->post($this->suiteUrl("pos/orders/{$order->id}/refunds"), ['lines' => [$order->lines->first()->id => 1], 'method' => 'card'])->assertForbidden();
    }
}
