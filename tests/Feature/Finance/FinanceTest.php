<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\JournalLine;
use App\Modules\Finance\Models\PurchaseOrder;
use App\Modules\Finance\Models\Supplier;
use App\Modules\Hr\Models\ExpenseClaim;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Pos\Actions\PayOrder;
use App\Modules\Pos\Actions\PlaceOrder;
use App\Modules\Pos\Actions\SaveProduct;
use App\Modules\Pos\Models\Category;
use App\Modules\Pos\Models\Order;
use App\Modules\Pos\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

/**
 * One ledger for the whole account: POS sales, expense claims and supplier bills post to it per
 * company and branch, and the reports (trial balance, P&L, VAT return) read from it.
 */
class FinanceTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-10-10 10:00:00');
        $this->asTenant();
        Storage::fake(config('baydaq.hr.disk'));
    }

    private function sell(): Order
    {
        $category = (new Category(['company_id' => $this->company->id, 'name_ar' => 'مشروبات', 'name_en' => 'Drinks']))->forceFill(['client_id' => $this->client->id]);
        $category->save();
        $latte = app(SaveProduct::class)->handle(new Product, ['company_id' => $this->company->id, 'category_id' => $category->id, 'name_ar' => 'لاتيه', 'name_en' => 'Latte', 'price' => '23.00']);
        $order = app(PlaceOrder::class)->handle(new Order, $this->headOffice->id, null, null, [], [['product_id' => $latte->id, 'quantity' => 5]]); // 115 incl. VAT

        return app(PayOrder::class)->handle($order, [['method' => 'cash', 'amount' => '15.00'], ['method' => 'mada', 'amount' => '100.00']]);
    }

    private function balanceOf(string $key): float
    {
        $account = Account::query()->where('system_key', $key)->sole();
        $lines = JournalLine::query()->where('account_id', $account->id);
        [$debit, $credit] = [(float) (clone $lines)->sum('debit'), (float) (clone $lines)->sum('credit')];

        return $account->isDebitNatured() ? $debit - $credit : $credit - $debit;
    }

    public function test_a_pos_sale_posts_each_payment_method_with_output_vat(): void
    {
        $order = $this->sell();
        $entry = JournalEntry::query()->where('source', 'pos.order')->sole();

        $this->assertSame([$order->branch_id, $order->id], [$entry->branch_id, $entry->source_id]);
        $this->assertEquals(15, $this->balanceOf('cash'));
        $this->assertEquals(100, $this->balanceOf('card_clearing'));
        $this->assertEquals(100, $this->balanceOf('sales'));
        $this->assertEquals(15, $this->balanceOf('vat_output'));
    }

    public function test_purchase_to_payment_and_the_vat_return(): void
    {
        $this->sell();
        $this->actingAs($this->owner)->post($this->suiteUrl('finance/suppliers'), ['name' => 'Al Marai', 'vat_number' => '300000000000003', 'payment_days' => 30])->assertSessionHasNoErrors();
        $supplier = Supplier::query()->sole();
        $milk = Account::query()->where('system_key', 'purchases')->sole();

        $this->actingAs($this->owner)->post($this->suiteUrl('finance/orders'), [
            'branch_id' => $this->headOffice->id, 'supplier_id' => $supplier->id, 'order_date' => '2026-10-10',
            'lines' => [['description' => 'Milk 1L', 'quantity' => 20, 'unit_price' => '2.00', 'vat_rate' => 15, 'account_id' => $milk->id]],
        ])->assertSessionHasNoErrors();
        $order = PurchaseOrder::query()->sole();
        $this->assertSame(['PO-00001', '46.00'], [$order->number, (string) $order->total]);

        foreach (['approved', 'received', 'billed'] as $status) {
            $this->actingAs($this->owner)->put($this->suiteUrl("finance/orders/{$order->id}/status"), ['status' => $status, 'supplier_invoice_no' => 'INV-9'])->assertSessionHasNoErrors();
        }

        $bill = Bill::query()->sole();
        $this->assertSame(['draft', '2026-11-09'], [$bill->status, $bill->due_on->toDateString()]);
        $this->actingAs($this->owner)->put($this->suiteUrl("finance/bills/{$bill->id}/approve"))->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post($this->suiteUrl("finance/bills/{$bill->id}/payments"), ['amount' => '50', 'paid_on' => '2026-10-10', 'method' => 'bank'])->assertSessionHasErrors('amount');
        $this->actingAs($this->owner)->post($this->suiteUrl("finance/bills/{$bill->id}/payments"), ['amount' => '46', 'paid_on' => '2026-10-10', 'method' => 'bank'])->assertSessionHasNoErrors();

        $this->assertSame('paid', $bill->fresh()->status);
        $this->assertEquals(0, $this->balanceOf('payables'));

        $this->actingAs($this->owner)->get($this->suiteUrl('finance/reports?from=2026-10-01&to=2026-10-31'))->assertInertia(fn (Assert $page) => $page
            ->component('Finance/Reports')
            ->where('vat.sales', '100.00')->where('vat.output_vat', '15.00')
            ->where('vat.purchases', '40.00')->where('vat.input_vat', '6.00')->where('vat.net_due', '9.00')
            ->where('profit_and_loss.profit', '60.00'));
    }

    public function test_an_approved_expense_claim_is_booked_and_paid_back(): void
    {
        $employee = $this->employeeAt();
        $user = User::factory()->create(['client_id' => $this->client->id]);
        $employee->forceFill(['user_id' => $user->id])->save();

        $this->actingAs($user)->post('/me/expenses', [
            'spent_on' => '2026-10-09', 'category' => 'fuel', 'amount' => '115', 'vat' => '15', 'receipt' => UploadedFile::fake()->image('r.jpg'),
        ])->assertSessionHasErrors('vat'); // VAT needs the station's VAT number
        $this->actingAs($user)->post('/me/expenses', [
            'spent_on' => '2026-10-09', 'category' => 'fuel', 'amount' => '115', 'vat' => '15', 'merchant' => 'Aldrees', 'merchant_vat_number' => '300000000000003',
            'receipt' => UploadedFile::fake()->image('r.jpg'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $claim = ExpenseClaim::query()->sole();

        $this->actingAs($this->owner)->put($this->suiteUrl("hr/expenses/{$claim->id}/decision"), ['approve' => true])->assertSessionHasNoErrors();
        $this->assertEquals(100, $this->balanceOf('fuel'));
        $this->assertEquals(15, $this->balanceOf('vat_input'));
        $this->assertEquals(115, $this->balanceOf('employee_reimbursements'));

        $this->actingAs($this->owner)->put($this->suiteUrl("hr/expenses/{$claim->id}/paid"))->assertSessionHasNoErrors();
        $this->assertEquals(0, $this->balanceOf('employee_reimbursements'));
        $this->assertSame('paid', $claim->fresh()->status);
    }

    public function test_manual_entries_must_balance_and_the_trial_balance_does(): void
    {
        $this->sell();
        $accounts = Account::query()->whereIn('system_key', ['rent', 'bank'])->pluck('id', 'system_key');
        $entry = ['branch_id' => $this->headOffice->id, 'entry_date' => '2026-10-01', 'memo' => 'October rent', 'lines' => [
            ['account_id' => $accounts['rent'], 'debit' => '5000'], ['account_id' => $accounts['bank'], 'credit' => '4000'],
        ]];

        $this->actingAs($this->owner)->post($this->suiteUrl('finance/journal'), $entry)->assertSessionHasErrors('lines');
        $entry['lines'][1]['credit'] = '5000';
        $this->actingAs($this->owner)->post($this->suiteUrl('finance/journal'), $entry)->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->get($this->suiteUrl('finance/reports'))->assertInertia(function (Assert $page): void {
            $rows = collect($page->toArray()['props']['trial_balance']);
            $this->assertEquals($rows->sum('debit'), $rows->sum('credit'));
        });

        $this->actingAs($this->memberWith(['finance.ledger.view'], ScopeLevel::Client))->post($this->suiteUrl('finance/journal'), $entry)->assertForbidden();
    }
}
