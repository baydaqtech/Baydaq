<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Actions\Employees\LinkEmployeeUser;
use App\Modules\Hr\Actions\Leave\AdjustLeaveBalance;
use App\Modules\Hr\Actions\Leave\LeaveBalance;
use App\Modules\Hr\Actions\Leave\RequestLeave;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class LeaveBalanceTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-11-15 09:00:00');
    }

    private function type(array $rules = []): LeaveType
    {
        $type = (new LeaveType(['name_ar' => 'سنوية', 'name_en' => 'Annual', 'days_per_year' => '21', 'senior_days_per_year' => '30', 'senior_after_years' => 5, ...$rules]))->forceFill(['client_id' => $this->client->id]);
        $type->save();

        return $type;
    }

    private function balance(Employee $employee, LeaveType $type, ?string $on = null): array
    {
        return app(LeaveBalance::class)->handle($employee, $type, $on ? now()->parse($on) : null);
    }

    public function test_daily_and_yearly_accrual_in_the_hire_year(): void
    {
        $employee = $this->employeeAt(attributes: ['hired_on' => '2026-07-01']);

        $daily = $this->balance($employee, $this->type(['accrual' => 'daily']));
        $this->assertSame(['7.93', 138], [$daily['accrued'], $daily['units']]); // 21 ÷ 365 × 138 days (1 Jul – 15 Nov)

        $yearlyType = $this->type(['accrual' => 'yearly', 'name_en' => 'Annual 2', 'name_ar' => 'سنوية 2']);
        $this->assertSame('10.58', $this->balance($employee, $yearlyType)['accrued']); // the rest of 2026 up front: 21 ÷ 365 × 184
        $this->assertSame('21.00', $this->balance($employee, $yearlyType, '2027-01-01')['accrued']); // then the whole year on 1 January
    }

    public function test_the_leave_year_can_follow_the_hire_date(): void
    {
        $employee = $this->employeeAt(attributes: ['hired_on' => '2025-05-10']);
        $balance = $this->balance($employee, $this->type(['year_basis' => 'hire_date']));

        $this->assertSame(['2026-05-10', '2027-05-09'], [$balance['year_start'], $balance['year_end']]);
        $this->assertSame(['21.00', '10.50', 6], [$balance['carried'], $balance['accrued'], $balance['units']]); // first year rolled over; 6 months since 10 May
    }

    public function test_the_higher_rate_starts_on_the_fifth_anniversary(): void
    {
        $employee = $this->employeeAt(attributes: ['hired_on' => '2021-06-01']);
        $balance = $this->balance($employee, $this->type(['accrual' => 'daily', 'carry_over_days' => 0]), '2026-12-31');

        $this->assertSame('2026-06-01', $balance['senior_from']);
        $this->assertSame('26.27', $balance['accrued']); // (151 days × 21 + 214 days × 30) ÷ 365
        $this->assertSame('0.00', $balance['carried']);
    }

    public function test_carry_over_is_capped_and_adjustments_count(): void
    {
        $employee = $this->employeeAt(attributes: ['hired_on' => '2025-01-01']);
        $type = $this->type(['carry_over_days' => 5]);
        $this->assertSame('5.00', $this->balance($employee, $type)['carried']);

        app(AdjustLeaveBalance::class)->handle($employee, $type, '12.5', '2026-01-01', 'Opening balance from the old system');
        app(AdjustLeaveBalance::class)->handle($employee, $type, '-2', '2026-06-01', 'Correction');
        $balance = $this->balance($employee, $type);
        $this->assertSame(['10.50', '33.00'], [$balance['adjustments'], $balance['available']]); // 5 + 17.50 + 10.50

        $this->expectException(ValidationException::class);
        app(AdjustLeaveBalance::class)->handle($employee, $type, '0', '2026-06-01', 'Nothing');
    }

    public function test_pending_requests_count_against_what_can_be_requested(): void
    {
        $employee = $this->employeeAt(attributes: ['hired_on' => '2026-01-01']);
        $type = $this->type();

        app(RequestLeave::class)->handle($employee, $type, ['starts_on' => '2026-12-01', 'ends_on' => '2026-12-10']); // 10 of 19.25 available on 1 Dec
        $this->assertSame(['17.50', '10.00', '7.50'], array_values(array_intersect_key($this->balance($employee, $type), array_flip(['available', 'pending', 'available_after_pending']))));

        try {
            app(RequestLeave::class)->handle($employee, $type, ['starts_on' => '2026-12-14', 'ends_on' => '2026-12-23']);
            $this->fail('A second request beyond the balance was accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('days', $e->errors());
        }

        $type->forceFill(['allow_negative' => true])->save();
        $this->assertSame('pending', app(RequestLeave::class)->handle($employee, $type->fresh(), ['starts_on' => '2026-12-14', 'ends_on' => '2026-12-23'])->status->value);
    }

    public function test_an_employee_sees_their_balance_and_asks_for_leave_on_the_web_and_in_the_app(): void
    {
        $type = $this->type();
        $employee = $this->employeeAt(attributes: ['hired_on' => '2025-01-01']);
        app(LinkEmployeeUser::class)->handle($employee, 'huda@example.test');
        $user = User::query()->where('email', 'huda@example.test')->firstOrFail();
        $user->forceFill(['password' => bcrypt('secret-123'), 'must_change_password' => false])->save();

        $this->assertSame(route('me.home'), $user->fresh()->homeUrl()); // no role in the suite
        $this->actingAs($user)->get('/me')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Hr/Me')->has('balances', 1)->where('balances.0.available', '38.50')->where('balances.0.accrual', 'monthly'));
        $this->actingAs($user)->post('/me/leave', ['leave_type_id' => $type->id, 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-12'])->assertSessionHasErrors('starts_on'); // in the past
        $this->actingAs($user)->post('/me/leave', ['leave_type_id' => $type->id, 'starts_on' => '2026-11-22', 'ends_on' => '2026-11-24'])->assertSessionHasNoErrors();

        $token = $this->postJson('/api/v1/auth/login', ['email' => 'huda@example.test', 'password' => 'secret-123', 'device_id' => 'phone-9', 'platform' => 'android'])->assertOk()->json('token');
        $this->withToken($token)->getJson('/api/v1/leave')->assertOk()
            ->assertJsonPath('balances.0.pending', '3.00')->assertJsonPath('requests.0.status', 'pending')->assertJsonPath('types.0.id', $type->id);
        $this->withToken($token)->postJson('/api/v1/leave', ['leave_type_id' => $type->id, 'starts_on' => '2026-12-06', 'ends_on' => '2026-12-07'])->assertCreated()->assertJsonPath('days', '2.00');

        $stranger = User::factory()->create(['client_id' => $this->client->id]);
        $this->actingAs($stranger)->get('/me')->assertForbidden();
    }

    public function test_hr_adjusts_a_balance_from_the_employee_page(): void
    {
        $type = $this->type();
        $employee = $this->employeeAt(attributes: ['hired_on' => '2026-01-01']);

        $this->actingAs($this->owner)->post($this->suiteUrl("hr/employees/{$employee->id}/leave-adjustments"), ['leave_type_id' => $type->id, 'days' => '4', 'effective_on' => '2026-02-01', 'reason' => 'Opening balance'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->get($this->suiteUrl("hr/employees/{$employee->id}"))->assertInertia(fn (Assert $page) => $page
            ->where('leave.balances.0.adjustments', '4.00')->where('leave.balances.0.available', '21.50'));
    }
}
