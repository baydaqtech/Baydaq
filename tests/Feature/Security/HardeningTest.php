<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Finance\Actions\PurchasingFlow;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\Supplier;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\ExpenseClaim;
use App\Modules\Platform\Actions\Access\AssignAccess;
use App\Modules\Platform\Actions\Suite\SaveCompany;
use App\Modules\Platform\Data\LedgerPosting;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Access\AccessAssignment;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Subscription;
use App\Modules\Platform\Ports\Ledger;
use App\Modules\Pos\Actions\PayOrder;
use App\Modules\Pos\Actions\PlaceOrder;
use App\Modules\Pos\Actions\SaveProduct;
use App\Modules\Pos\Models\Category;
use App\Modules\Pos\Models\Order;
use App\Modules\Pos\Models\Product;
use Database\Seeders\TestAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

/**
 * Regression tests for the security and race-condition review: a second attempt at a one-time
 * action is refused even with a stale copy of the record, and users can't act outside their reach.
 */
class HardeningTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->asTenant();
    }

    private function product(int $companyId): Product
    {
        $category = (new Category(['company_id' => $companyId, 'name_ar' => 'مشروبات', 'name_en' => 'Drinks']))->forceFill(['client_id' => $this->client->id]);
        $category->save();

        return app(SaveProduct::class)->handle(new Product, ['company_id' => $companyId, 'category_id' => $category->id, 'name_ar' => 'لاتيه', 'name_en' => 'Latte', 'price' => '23.00']);
    }

    public function test_an_order_cannot_be_paid_twice_even_from_a_stale_copy(): void
    {
        $latte = $this->product($this->company->id);
        $order = app(PlaceOrder::class)->handle(new Order, $this->headOffice->id, null, null, [], [['product_id' => $latte->id, 'quantity' => 1]]);
        $stale = Order::query()->findOrFail($order->id);

        app(PayOrder::class)->handle($order, [['method' => 'cash', 'amount' => '23.00']]);

        $this->expectException(ValidationException::class);
        app(PayOrder::class)->handle($stale, [['method' => 'cash', 'amount' => '23.00']]);
    }

    public function test_a_bill_cannot_be_overpaid_from_a_stale_copy(): void
    {
        $supplier = (new Supplier(['name' => 'Al Marai', 'payment_days' => 30]))->forceFill(['client_id' => $this->client->id]);
        $supplier->save();
        $bill = (new Bill)->forceFill([
            'client_id' => $this->client->id, 'company_id' => $this->company->id, 'branch_id' => $this->headOffice->id, 'supplier_id' => $supplier->id,
            'bill_date' => today(), 'due_on' => today(), 'status' => 'approved', 'subtotal' => '1000', 'vat' => '0', 'total' => '1000', 'paid' => '0',
        ]);
        $bill->save();
        $stale = Bill::query()->findOrFail($bill->id);
        $flow = app(PurchasingFlow::class);

        $flow->payBill($bill, '800', today()->toDateString(), 'bank', null);

        $this->expectException(ValidationException::class);
        $flow->payBill($stale, '800', today()->toDateString(), 'bank', null);
    }

    public function test_the_ledger_records_a_source_only_once(): void
    {
        $posting = new LedgerPosting($this->company->id, null, today()->toDateString(), 'test.source', 7, 'x', [
            ['account' => 'cash', 'debit' => '10'], ['account' => 'sales', 'credit' => '10'],
        ]);

        app(Ledger::class)->post($posting);
        app(Ledger::class)->post($posting);

        $this->assertSame(1, JournalEntry::query()->where('source', 'test.source')->count());
    }

    public function test_a_branch_access_admin_cannot_revoke_the_owner(): void
    {
        $owners = AccessAssignment::query()->where('user_id', $this->owner->id)->get();
        $second = User::factory()->create(['client_id' => $this->client->id]);
        app(AssignAccess::class)->handle($second, $owners->first()->role, ScopeLevel::Client, null, actor: null, guard: false);
        $branchAdmin = $this->memberWith(['suite.access.delete', 'suite.access.view'], ScopeLevel::Branch, $this->headOffice);

        $this->actingAs($branchAdmin)->delete($this->suiteUrl("access/assignments/{$owners->first()->id}"))->assertSessionHasErrors('assignment');
        $this->assertTrue(AccessAssignment::query()->whereKey($owners->first()->id)->exists());
    }

    public function test_catalog_editors_cannot_reach_another_companys_products(): void
    {
        Subscription::factory()->module(Module::Pos)->create(['client_id' => $this->client->id]);
        $other = app(SaveCompany::class)->handle($this->organization->companies()->make(), ['name_ar' => 'أخرى', 'name_en' => 'Other', 'timezone' => 'Asia/Riyadh', 'status' => 'active']);
        $product = $this->product($other->id);
        $editor = $this->memberWith(['pos.catalog.view', 'pos.catalog.create', 'pos.catalog.update'], ScopeLevel::Company, $this->company);

        $this->actingAs($editor)->put($this->suiteUrl("pos/catalog/products/{$product->id}"), [
            'company_id' => $this->company->id, 'name_ar' => 'x', 'name_en' => 'x', 'price' => '1', 'vat_rate' => '15',
        ])->assertForbidden();
        $this->assertSame($other->id, $product->fresh()->company_id);
    }

    public function test_nobody_approves_their_own_expense_claim(): void
    {
        Storage::fake(config('baydaq.hr.disk'));
        $employee = $this->employeeAt();
        $employee->forceFill(['user_id' => $this->owner->id])->save();
        $this->actingAs($this->owner)->post('/me/expenses', ['spent_on' => today()->toDateString(), 'category' => 'fuel', 'amount' => '50', 'receipt' => UploadedFile::fake()->image('r.jpg')])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->put($this->suiteUrl('hr/expenses/'.ExpenseClaim::query()->sole()->id.'/decision'), ['approve' => true])->assertSessionHasErrors('status');
    }

    public function test_a_leaver_loses_the_mobile_app(): void
    {
        $employee = $this->employeeAt();
        $user = User::factory()->create(['client_id' => $this->client->id]);
        $employee->forceFill(['user_id' => $user->id])->save();
        $user->createToken('phone');

        $this->actingAs($this->owner)->put($this->suiteUrl("hr/employees/{$employee->id}/status"), ['status' => EmployeeStatus::Terminated->value])->assertSessionHasNoErrors();
        $this->assertSame(0, $user->tokens()->count());

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/record')->assertForbidden();
    }

    public function test_the_fake_payment_page_is_closed_in_production_and_pages_send_security_headers(): void
    {
        $this->get('/')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->app['env'] = 'production';
        $this->get('/pay/'.str_repeat('a', 40))->assertNotFound();
    }

    public function test_the_test_account_seeder_builds_one_owner_two_companies_and_five_employees(): void
    {
        $this->seed(TestAccountSeeder::class);

        $owner = User::query()->where('email', TestAccountSeeder::OWNER)->sole();
        $this->asTenant($owner->client);
        $this->assertSame(2, Company::query()->count());
        $this->assertSame(5, Branch::query()->count());
        $this->assertSame(5, Employee::query()->whereNotNull('user_id')->count());
    }
}
