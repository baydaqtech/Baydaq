<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Actions\Contracts\SaveContract;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Models\AttendancePunch;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Deduction;
use App\Modules\Hr\Models\DeductionType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Separation;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Models\Violation;
use App\Modules\Platform\Enums\ScopeLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

/**
 * Leaving under the 2025 Labor Law amendments, and the disciplinary record.
 */
class OffboardingAndViolationsTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private Employee $employee;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-10-01 09:00:00');
        $this->asTenant();
        $this->employee = $this->employeeAt(attributes: ['hired_on' => '2022-01-01']);
        app(SaveContract::class)->handle($this->employee, new Contract, ['type' => 'open_ended', 'status' => 'active', 'starts_on' => '2022-01-01', 'basic_salary' => '9000'], []);
        $this->user = User::factory()->create(['client_id' => $this->client->id]);
        $this->employee->forceFill(['user_id' => $this->user->id])->save();
    }

    public function test_a_resignation_nobody_answers_counts_as_accepted_after_thirty_days(): void
    {
        $this->actingAs($this->user)->post('/me/resignation', ['reason' => 'Moving city'])->assertSessionHasNoErrors();
        $separation = Separation::query()->sole();
        $this->assertSame(['submitted', '2026-10-31', '2026-10-31'], [$separation->status, $separation->last_day->toDateString(), $separation->deemed_accept_on->toDateString()]);

        $this->actingAs($this->user)->post('/me/resignation')->assertSessionHasErrors('kind');

        $this->travelTo('2026-10-31 01:00:00');
        $this->artisan('hr:accept-overdue-resignations')->assertSuccessful();
        $this->asTenant();
        $separation->refresh();
        $this->assertSame('accepted', $separation->status);
        $this->assertTrue($separation->deemed);

        $this->actingAs($this->owner)->put($this->suiteUrl("hr/separations/{$separation->id}"), ['action' => 'complete'])->assertSessionHasNoErrors();
        $this->assertSame(EmployeeStatus::Terminated, $this->employee->fresh()->status);
        $this->assertSame('2026-10-31', $this->employee->fresh()->terminated_on->toDateString());
    }

    public function test_the_employee_may_withdraw_within_seven_days_and_the_employer_may_defer_up_to_sixty(): void
    {
        $this->actingAs($this->user)->post('/me/resignation')->assertSessionHasNoErrors();
        $separation = Separation::query()->sole();

        $this->actingAs($this->owner)->put($this->suiteUrl("hr/separations/{$separation->id}"), ['action' => 'defer', 'last_day' => '2026-12-15'])->assertSessionHasErrors('last_day');
        $this->actingAs($this->owner)->put($this->suiteUrl("hr/separations/{$separation->id}"), ['action' => 'defer', 'last_day' => '2026-11-25'])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-25', $separation->fresh()->deemed_accept_on->toDateString());

        $this->travelTo('2026-10-09 09:00:00');
        $this->actingAs($this->user)->post("/me/resignation/{$separation->id}/withdraw")->assertSessionHasErrors('action');

        $this->travelTo('2026-10-05 09:00:00');
        $this->actingAs($this->user)->post("/me/resignation/{$separation->id}/withdraw")->assertSessionHasNoErrors();
        $this->assertSame('withdrawn', $separation->fresh()->status);
    }

    public function test_the_mobile_app_shows_my_record_and_lets_me_resign(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/resignation', ['last_day' => '2026-11-15'])->assertCreated()->assertJsonPath('status', 'submitted');
        $this->getJson('/api/v1/record')->assertOk()->assertJsonPath('separation.last_day', '2026-11-15')->assertJsonPath('separation.can_withdraw', true);
    }

    public function test_a_violation_is_counted_heard_and_penalised_within_the_caps(): void
    {
        $type = (new DeductionType(['name_ar' => 'جزاء', 'name_en' => 'Penalty']))->forceFill(['client_id' => $this->client->id]);
        $type->save();

        foreach (['2026-08-01', '2026-09-20'] as $date) {
            $this->actingAs($this->owner)->post($this->suiteUrl("hr/employees/{$this->employee->id}/violations"), ['category' => 'late', 'occurred_on' => $date, 'minutes' => 30])->assertSessionHasNoErrors();
        }
        $violation = Violation::query()->latest('id')->first();
        $this->assertSame(2, $violation->occurrence);

        $close = ['action' => 'close', 'penalty' => 'deduction', 'deduction_days' => 1, 'deduction_type_id' => $type->id];
        $this->actingAs($this->owner)->put($this->suiteUrl("hr/violations/{$violation->id}"), $close)->assertSessionHasErrors('penalty');

        $this->actingAs($this->owner)->put($this->suiteUrl("hr/violations/{$violation->id}"), ['action' => 'notify'])->assertSessionHasNoErrors();
        $this->actingAs($this->user)->post("/me/violations/{$violation->id}/response", ['response' => 'Traffic accident on the way.'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->put($this->suiteUrl("hr/violations/{$violation->id}"), [...$close, 'deduction_days' => 6])->assertSessionHasErrors('deduction_days');
        $this->actingAs($this->owner)->put($this->suiteUrl("hr/violations/{$violation->id}"), $close)->assertSessionHasNoErrors();

        $deduction = Deduction::query()->sole();
        $this->assertSame('violation', $deduction->origin);
        $this->assertSame('300.00', (string) $deduction->amount); // 9000 / 30
        $this->assertSame($deduction->id, $violation->fresh()->deduction_id);

        $viewer = $this->memberWith(['hr.violations.view'], ScopeLevel::Client);
        $this->actingAs($viewer)->put($this->suiteUrl("hr/violations/{$violation->id}"), ['action' => 'cancel'])->assertForbidden();
    }

    public function test_late_arrivals_from_the_roster_are_offered_for_recording(): void
    {
        (new Shift)->forceFill([
            'client_id' => $this->client->id, 'company_id' => $this->company->id, 'branch_id' => $this->headOffice->id, 'employee_id' => $this->employee->id,
            'shift_date' => '2026-10-01', 'starts_at' => '08:00:00', 'ends_at' => '16:00:00', 'minutes' => 480,
        ])->save();
        (new AttendancePunch)->forceFill([
            'client_id' => $this->client->id, 'employee_id' => $this->employee->id, 'type' => 'in', 'punched_at' => '2026-10-01 05:40:00', 'work_date' => '2026-10-01', 'home_branch_id' => $this->headOffice->id, 'source' => 'manual',
        ])->save(); // 08:40 Riyadh

        $this->actingAs($this->owner)->get($this->suiteUrl('hr/violations?from=2026-10-01&to=2026-10-01'))->assertInertia(fn (Assert $page) => $page
            ->component('Hr/Violations/Index')->where('late.0.minutes', 40)->where('late.0.punched_at', '08:40'));
    }
}
