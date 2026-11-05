<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Finance\Actions\PurchasingFlow;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\Supplier;
use App\Modules\Hr\Actions\Breaks\BreakFlow;
use App\Modules\Hr\Actions\Employees\ChangeEmployeeStatus;
use App\Modules\Hr\Actions\Pay\RecordRepayment;
use App\Modules\Hr\Actions\Payroll\BuildPayrollRun;
use App\Modules\Hr\Enums\AdvanceStatus;
use App\Modules\Hr\Enums\BreakStatus;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\RepaymentMethod;
use App\Modules\Hr\Models\Advance;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\ExpenseClaim;
use App\Modules\Hr\Models\WorkBreak;
use App\Modules\Invoicing\Actions\IssueInvoice;
use App\Modules\Invoicing\Actions\SaveInvoice;
use App\Modules\Invoicing\Enums\InvoiceStatus;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Platform\Actions\Access\AssignAccess;
use App\Modules\Platform\Actions\Plans\ApplyPlan;
use App\Modules\Platform\Actions\Suite\SaveBranch;
use App\Modules\Platform\Actions\Suite\SaveCompany;
use App\Modules\Platform\Data\LedgerPosting;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Access\AccessAssignment;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\ClientPlan;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\CustomerRegistration;
use App\Modules\Platform\Models\Message;
use App\Modules\Platform\Models\Package;
use App\Modules\Platform\Models\Payment;
use App\Modules\Platform\Models\Subscription;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Ports\Ledger;
use App\Modules\Platform\Ports\Payments;
use App\Modules\Pos\Actions\PayOrder;
use App\Modules\Pos\Actions\PlaceOrder;
use App\Modules\Pos\Actions\SaveProduct;
use App\Modules\Pos\Actions\VoidOrder;
use App\Modules\Pos\Models\Category;
use App\Modules\Pos\Models\Order;
use App\Modules\Pos\Models\Product;
use App\Modules\Reservations\Actions\BookAppointment;
use App\Modules\Reservations\Actions\CheckoutBooking;
use App\Modules\Reservations\Actions\SaveService;
use App\Modules\Reservations\Enums\BookingChannel;
use App\Modules\Reservations\Enums\PaymentMeans;
use App\Modules\Reservations\Models\Booking;
use App\Modules\Reservations\Models\Resource;
use App\Modules\Reservations\Models\Service;
use Database\Seeders\TestAccountSeeder;
use Faker\Generator as FakerGenerator;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;
use Tests\Concerns\BuildsSuite;
use Tests\Concerns\FakesZatca;
use Tests\TestCase;

/**
 * Regression tests for the security and race-condition review: a second attempt at a one-time
 * action is refused even with a stale copy of the record, and users can't act outside their reach.
 */
class HardeningTest extends TestCase
{
    use BuildsSuite, FakesZatca, RefreshDatabase;

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

    /**
     * The first statement an action runs inside its first transaction. One-time actions must
     * open with the locking read: under REPEATABLE READ any earlier read fixes the snapshot.
     */
    private function firstStatementInTransaction(callable $action): string
    {
        $began = false;
        $statements = [];
        Event::listen(TransactionBeginning::class, function () use (&$began): void {
            $began = true;
        });
        DB::listen(function (QueryExecuted $query) use (&$began, &$statements): void {
            if ($began) {
                $statements[] = $query->sql;
            }
        });

        $action();

        return $statements[0] ?? '';
    }

    /**
     * Every statement an action runs.
     *
     * @return list<string>
     */
    private function statementsDuring(callable $action): array
    {
        $statements = [];
        DB::listen(function (QueryExecuted $query) use (&$statements): void {
            $statements[] = $query->sql;
        });

        $action();

        return $statements;
    }

    private function bookableService(): Service
    {
        $room = (new Resource(['name_ar' => 'غرفة', 'name_en' => 'Room', 'kind' => 'room']))->forceFill(['client_id' => $this->client->id, 'company_id' => $this->company->id, 'branch_id' => $this->headOffice->id]);
        $room->save();

        return app(SaveService::class)->handle(new Service, ['company_id' => $this->company->id, 'name_ar' => 'جلسة', 'name_en' => 'Session', 'duration_minutes' => 30, 'price' => '50.00'], [$room->id], []);
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

    public function test_the_test_account_seeder_runs_on_a_server_without_faker(): void
    {
        // A production install (composer install --no-dev) has no Faker, so factories can't run there.
        $noFaker = fn () => throw new RuntimeException('Faker is not installed on this server.');
        $this->app->bind(FakerGenerator::class, $noFaker);
        $this->app->bind(FakerGenerator::class.':'.config('app.faker_locale'), $noFaker);

        $this->seed(TestAccountSeeder::class);

        $owner = User::query()->where('email', TestAccountSeeder::OWNER)->sole();
        $this->assertTrue($owner->hasRole('client'));
        $this->assertTrue(Hash::check(TestAccountSeeder::PASSWORD, $owner->password));
        $this->asTenant($owner->client);
        $this->assertSame(5, Employee::query()->whereNotNull('user_id')->count());
        $this->assertTrue(Subscription::query()->where('ends_on', '>', today())->exists());
    }

    public function test_a_booking_locks_the_branch_before_it_reads_the_free_slots(): void
    {
        $this->travelTo('2026-11-01 08:00:00');
        $service = $this->bookableService();

        $sql = $this->firstStatementInTransaction(fn () => app(BookAppointment::class)->handle(
            $service, $this->headOffice->id, '2026-11-01T12:00:00+03:00', ['customer_name' => 'Fahad', 'customer_phone' => '0500000000'], BookingChannel::Staff,
        ));

        $this->assertMatchesRegularExpression('/^select .* from `res__branch_settings` .* for update$/', $sql);
    }

    public function test_an_invoice_zatca_accepted_stays_issued_when_a_follow_up_fails(): void
    {
        Company::query()->whereKey($this->company->id)->update(['vat_number' => '300000000000003', 'street' => 'King Fahd Rd', 'building_number' => '1234', 'district' => 'Olaya', 'city' => 'Riyadh', 'postal_code' => '12211']);
        $unit = $this->readyUnit($this->company->id, $this->client->id)['unit'];
        $draft = app(SaveInvoice::class)->handle(new Invoice, $this->company->id, ['type' => 'simplified', 'issue_date' => today()->toDateString(), 'payment_means' => '10'], [
            ['description' => 'Coffee', 'quantity' => 1, 'unit_price' => '20.00'],
        ]);
        $this->fakeZatcaAccepting();
        $this->mock(Ledger::class)->shouldReceive('post')->andThrow(new RuntimeException('The books are down.'));

        try {
            app(IssueInvoice::class)->handle($draft, $unit);
            $this->fail('The ledger failure should surface.');
        } catch (RuntimeException) {
            // ZATCA holds the invoice; so must we.
        }

        $this->assertSame(InvoiceStatus::Reported, $draft->fresh()->status);
        $this->assertSame(1, $unit->fresh()->counter);
        $this->assertSame('accepted', $draft->submissions()->sole()->status);
    }

    public function test_owners_must_turn_on_two_step_sign_in_when_the_deployment_requires_it(): void
    {
        config(['baydaq.security.two_factor_required' => true]);
        $this->actingAs($this->owner)->get($this->suiteUrl('hr/employees'))->assertRedirect(route('account.security'));
        $this->actingAs($this->owner)->get($this->suiteUrl('structure'))->assertRedirect(route('account.security'));
        $this->actingAs($this->owner)->get('/suite')->assertRedirect(route('account.security'));

        $session = ['auth.password_confirmed_at' => time()];
        $this->actingAs($this->owner)->withSession($session)->post('/user/two-factor-authentication')->assertRedirect();
        $this->actingAs($this->owner)->withSession($session)->get('/account/security')->assertInertia(fn ($page) => $page->component('Auth/TwoFactor')->where('pending', true)->where('required', true));

        $code = app(Google2FA::class)->getCurrentOtp(Fortify::currentEncrypter()->decrypt($this->owner->fresh()->two_factor_secret));
        $this->actingAs($this->owner)->withSession($session)->post('/user/confirmed-two-factor-authentication', ['code' => $code])->assertSessionHasNoErrors();

        $this->assertTrue($this->owner->fresh()->hasTwoFactorEnabled());
        $this->actingAs($this->owner->fresh())->get($this->suiteUrl('hr/employees'))->assertOk();
    }

    public function test_sign_in_asks_for_the_authenticator_code_once_two_step_is_on(): void
    {
        $secret = app(Google2FA::class)->generateSecretKey();
        $this->owner->forceFill(['two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret), 'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['recover-me-1'])), 'two_factor_confirmed_at' => now()])->save();

        $this->post('/login', ['email' => $this->owner->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => app(Google2FA::class)->getCurrentOtp($secret)]);
        $this->assertAuthenticatedAs($this->owner);
    }

    public function test_app_tokens_expire_and_the_app_renews_them_before_they_do(): void
    {
        $employee = $this->employeeAt();
        $user = User::factory()->create(['client_id' => $this->client->id]);
        $employee->forceFill(['user_id' => $user->id])->save();

        $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password', 'device_id' => 'phone-1', 'platform' => 'ios'])->assertOk();
        $this->assertTrue(now()->addDays(90)->isSameDay($login->json('expires_at')));

        $renewed = $this->withToken($login->json('token'))->postJson('/api/v1/auth/refresh')->assertOk();
        $this->assertSame(1, $user->tokens()->count());

        $this->travel(91)->days();
        $this->app['auth']->forgetGuards();
        $this->withToken($renewed->json('token'))->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_a_suspended_plan_turns_features_off_and_an_account_without_a_plan_gets_the_default_package(): void
    {
        $package = (new Package)->forceFill([
            'code' => 'basic', 'name_ar' => 'أساسية', 'name_en' => 'Basic', 'price_monthly' => '0', 'modules' => ['hr'],
            'features' => ['hr.tasks'], 'mobile' => ['mobile.attendance'], 'limits' => ['companies' => 1], 'is_default' => true,
        ]);
        $package->save();
        $entitlements = fn (): Entitlements => app(Entitlements::class);

        $this->assertTrue($entitlements()->hasFeature('hr.tasks'));
        $this->assertFalse($entitlements()->hasFeature('hr.loans'));
        $this->assertSame(1, $entitlements()->limit('companies'));

        app(ApplyPlan::class)->handle($this->client, $package, [], [], 'suspended', today()->toDateString(), null);
        $this->assertFalse($entitlements()->hasFeature('hr.tasks'));
        $this->assertSame([], $entitlements()->mobileFeatures());

        // No plan and no default package: nothing in production (everything is for demos only).
        ClientPlan::query()->delete();
        $package->forceFill(['is_default' => false])->save();
        $this->app['env'] = 'production';
        $this->assertSame([], $entitlements()->features());
    }

    public function test_only_account_wide_access_admins_create_or_change_roles(): void
    {
        $branchAdmin = $this->memberWith(['suite.access.view', 'suite.access.create', 'suite.access.update', 'hr.employees.view'], ScopeLevel::Branch, $this->headOffice);
        $role = AccessAssignment::query()->where('user_id', $branchAdmin->id)->sole()->role;

        $this->actingAs($branchAdmin)->post($this->suiteUrl('access/roles'), ['name_ar' => 'مشرف', 'name_en' => 'Supervisor', 'permissions' => ['hr.employees.view']])->assertForbidden();
        $this->actingAs($branchAdmin)->put($this->suiteUrl("access/roles/{$role->id}"), ['name_ar' => 'x', 'name_en' => 'x', 'permissions' => ['hr.employees.view']])->assertForbidden();
        $this->actingAs($branchAdmin)->get($this->suiteUrl('access/roles'))->assertInertia(fn ($page) => $page->where('manage.create', false)->where('manage.update', false));

        $this->actingAs($this->owner)->post($this->suiteUrl('access/roles'), ['name_ar' => 'مشرف', 'name_en' => 'Supervisor', 'permissions' => ['hr.employees.view']])->assertSessionHasNoErrors();
    }

    public function test_the_seat_count_on_hiring_is_a_locking_read(): void
    {
        $employee = $this->employeeAt();
        $employee->forceFill(['status' => EmployeeStatus::Draft->value])->save();

        $statements = $this->statementsDuring(fn () => app(ChangeEmployeeStatus::class)->handle($employee->fresh(), EmployeeStatus::Active));

        $this->assertNotEmpty(preg_grep('/^select count\(\*\) as `aggregate` from `hr__employees` .* lock in share mode$/', $statements));
    }

    public function test_an_approved_payroll_run_is_locked_before_a_rebuild(): void
    {
        app(BuildPayrollRun::class)->handle($this->company->id, today()->format('Y-m'));

        $sql = $this->firstStatementInTransaction(fn () => app(BuildPayrollRun::class)->handle($this->company->id, today()->format('Y-m')));

        $this->assertMatchesRegularExpression('/^select `status` from `hr__payroll_runs` .* for update$/', $sql);
    }

    public function test_two_repayments_cannot_both_fit_the_same_balance(): void
    {
        $advance = (new Advance)->forceFill([
            'client_id' => $this->client->id, 'employee_id' => $this->employeeAt()->id, 'amount' => '1000', 'installments' => 1,
            'first_installment_month' => today()->startOfMonth(), 'status' => AdvanceStatus::Disbursed->value,
        ]);
        $advance->save();

        $sql = $this->firstStatementInTransaction(fn () => app(RecordRepayment::class)->handle($advance, '600', RepaymentMethod::Cash));
        $this->assertMatchesRegularExpression('/^select \* from `hr__advances` .* for update$/', $sql);

        $this->expectException(ValidationException::class);
        app(RecordRepayment::class)->handle($advance, '600', RepaymentMethod::Cash);
    }

    public function test_a_break_request_cannot_be_decided_twice_from_a_stale_copy(): void
    {
        $employee = $this->employeeAt();
        $break = (new WorkBreak)->forceFill([
            'client_id' => $this->client->id, 'company_id' => $this->company->id, 'branch_id' => $this->headOffice->id, 'employee_id' => $employee->id,
            'kind' => 'requested', 'status' => BreakStatus::Requested->value, 'minutes' => 30, 'work_date' => today(), 'requested_at' => now(),
        ]);
        $break->save();
        $stale = WorkBreak::query()->findOrFail($break->id);

        app(BreakFlow::class)->decide($break, false, $this->owner);

        $this->expectException(ValidationException::class);
        app(BreakFlow::class)->decide($stale, true, $this->owner);
    }

    public function test_a_booking_cannot_be_checked_out_twice_from_a_stale_copy(): void
    {
        $this->travelTo('2026-11-01 08:00:00');
        $booking = app(BookAppointment::class)->handle($this->bookableService(), $this->headOffice->id, '2026-11-01T12:00:00+03:00', ['customer_name' => 'Fahad', 'customer_phone' => '0500000000'], BookingChannel::Staff);
        $stale = Booking::query()->findOrFail($booking->id);

        app(CheckoutBooking::class)->handle($booking, PaymentMeans::Cash);

        $this->expectException(ValidationException::class);
        app(CheckoutBooking::class)->handle($stale, PaymentMeans::Cash);
    }

    public function test_a_paid_order_cannot_be_voided_or_edited_from_a_stale_copy(): void
    {
        $latte = $this->product($this->company->id);
        $order = app(PlaceOrder::class)->handle(new Order, $this->headOffice->id, null, null, [], [['product_id' => $latte->id, 'quantity' => 1]]);
        $stale = Order::query()->findOrFail($order->id);
        app(PayOrder::class)->handle($order, [['method' => 'cash', 'amount' => '23.00']]);

        try {
            app(PlaceOrder::class)->handle($stale, $this->headOffice->id, null, null, [], [['product_id' => $latte->id, 'quantity' => 2]]);
            $this->fail('A paid order must not be edited.');
        } catch (ValidationException) {
            $this->assertSame('23.00', (string) $order->fresh()->total);
        }

        $this->expectException(ValidationException::class);
        app(VoidOrder::class)->handle($stale, 'oops', $this->owner);
    }

    public function test_plan_limits_lock_the_account_before_counting(): void
    {
        (new Package)->forceFill(['code' => 'small', 'name_ar' => 'صغيرة', 'name_en' => 'Small', 'price_monthly' => '0', 'modules' => [], 'features' => [], 'mobile' => [], 'limits' => ['branches' => 10], 'is_default' => true])->save();

        $statements = $this->statementsDuring(fn () => app(SaveBranch::class)->handle($this->company->branches()->make(), ['name_ar' => 'فرع', 'name_en' => 'Branch', 'code' => 'NEW']));

        $this->assertNotEmpty(preg_grep('/^select \* from `clients` where `clients`.`id` = \? limit 1 for update$/', $statements));
        $this->assertNotEmpty(preg_grep('/^select count\(\*\) as `aggregate` from `platform__branches` .* lock in share mode$/', $statements));
    }

    public function test_a_moyasar_webhook_is_believed_only_when_moyasar_confirms_the_full_amount(): void
    {
        config(['baydaq.payments.driver' => 'moyasar', 'baydaq.payments.moyasar.secret_key' => 'sk_test', 'baydaq.payments.moyasar.webhook_secret' => 'whsec']);
        Http::fake([
            'api.moyasar.com/v1/invoices' => Http::response(['id' => 'inv_9', 'url' => 'https://checkout.moyasar.com/invoices/inv_9']),
            'api.moyasar.com/v1/invoices/inv_9' => Http::sequence()
                ->push(['id' => 'inv_9', 'status' => 'paid', 'amount' => 100, 'currency' => 'SAR'])     // a 1-riyal payment
                ->push(['id' => 'inv_9', 'status' => 'paid', 'amount' => 25000, 'currency' => 'SAR']),
        ]);
        app(Payments::class)->link('test.thing:9', '250.00', 'Deposit');
        $webhook = ['type' => 'payment_paid', 'secret_token' => 'whsec', 'data' => ['id' => 'pay_9', 'invoice_id' => 'inv_9', 'status' => 'paid']];

        $this->postJson('/payments/webhook/moyasar', $webhook)->assertOk();
        $this->assertSame('pending', Payment::query()->sole()->status);

        $this->postJson('/payments/webhook/moyasar', $webhook)->assertOk();
        $this->assertSame('paid', Payment::query()->sole()->status);
    }

    public function test_marketing_consent_at_the_public_sign_up_needs_the_code_sent_to_that_phone(): void
    {
        $token = Str::random(32);
        $this->headOffice->forceFill(['join_token' => $token])->save();
        $form = ['phone' => '0559876543', 'name' => 'Sara', 'accept' => true, 'marketing_consent' => true];

        $this->post("/join/{$token}", $form)->assertSessionHasNoErrors();
        $this->assertSame(0, CustomerRegistration::query()->count());
        $this->get("/join/{$token}")->assertInertia(fn ($page) => $page->where('verifying', '••••••••6543'));

        $code = Message::query()->where('template', 'join_code')->sole()->params['code'];
        $this->post("/join/{$token}/verify", ['code' => $code === '111111' ? '222222' : '111111'])->assertSessionHasErrors('code');
        $this->assertSame(0, CustomerRegistration::query()->count());

        $this->post("/join/{$token}/verify", ['code' => $code])->assertSessionHasNoErrors();
        $this->assertNotNull(CustomerRegistration::query()->sole()->marketing_consent_at);
    }
}
