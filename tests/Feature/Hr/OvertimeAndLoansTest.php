<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Actions\Contracts\SaveContract;
use App\Modules\Hr\Enums\AdjustmentStatus;
use App\Modules\Hr\Models\Advance;
use App\Modules\Hr\Models\Bonus;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Overtime;
use App\Modules\Hr\Support\HrSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

/**
 * Overtime is paid through payroll as an approved "Overtime" bonus (Art. 107); loans are advances of
 * another kind with longer limits and an installment cap.
 */
class OvertimeAndLoansTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private Employee $employee;

    private User $login;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-11-10 10:00:00');
        $this->login = User::factory()->create(['client_id' => $this->client->id]);
        $this->employee = $this->employeeAt(attributes: ['user_id' => $this->login->id]);
        app(SaveContract::class)->handle($this->employee, new Contract, ['type' => 'open_ended', 'status' => 'active', 'starts_on' => '2026-01-01', 'basic_salary' => '5000'], []);
    }

    public function test_approved_overtime_becomes_a_payroll_bonus_at_the_labor_law_rate(): void
    {
        $this->actingAs($this->login)->post('/me/overtime', ['work_date' => '2026-11-08', 'hours' => '4', 'reason' => 'Stock count'])->assertRedirect()->assertSessionHasNoErrors();
        $overtime = Overtime::query()->sole();
        $this->assertSame('pending', $overtime->status);

        // One request per day.
        $this->actingAs($this->login)->post('/me/overtime', ['work_date' => '2026-11-08', 'hours' => '1'])->assertSessionHasErrors('work_date');

        $this->actingAs($this->owner)->put($this->suiteUrl("hr/overtime/{$overtime->id}"), ['approve' => true])->assertRedirect()->assertSessionHasNoErrors();

        // 5000 / (30 × 8) = 20.8333 an hour, plus 50 % of the basic hourly wage = 31.25; × 4 hours.
        $overtime->refresh();
        $this->assertSame('approved', $overtime->status);
        $this->assertSame('125.00', (string) $overtime->amount);
        $bonus = Bonus::query()->with('type')->sole();
        $this->assertSame($overtime->bonus_id, $bonus->id);
        $this->assertSame(AdjustmentStatus::Approved, $bonus->status);
        $this->assertSame('2026-11', $bonus->pay_month->format('Y-m'));
        $this->assertSame('overtime', $bonus->type->system_key);

        // Deciding twice is refused.
        $this->actingAs($this->owner)->put($this->suiteUrl("hr/overtime/{$overtime->id}"), ['approve' => false])->assertSessionHasErrors('status');

        $this->actingAs($this->owner)->get($this->suiteUrl('hr/overtime?status=approved'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Hr/Overtime/Index')->where('items.0.amount', '125.00'));
    }

    public function test_the_yearly_cap_and_dates_are_enforced(): void
    {
        app(HrSettings::class)->set('overtime_max_hours_year', 5);
        $this->actingAs($this->owner)->post($this->suiteUrl('hr/overtime'), ['employee_id' => $this->employee->id, 'work_date' => '2026-11-12', 'hours' => '2'])->assertSessionHasErrors('work_date');

        $this->actingAs($this->owner)->post($this->suiteUrl('hr/overtime'), ['employee_id' => $this->employee->id, 'work_date' => '2026-11-01', 'hours' => '4'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post($this->suiteUrl('hr/overtime'), ['employee_id' => $this->employee->id, 'work_date' => '2026-11-02', 'hours' => '2'])->assertSessionHasNoErrors();
        [$first, $second] = Overtime::query()->orderBy('work_date')->get()->all();
        $this->assertSame('manager', $first->source);

        $this->actingAs($this->owner)->put($this->suiteUrl("hr/overtime/{$first->id}"), ['approve' => true])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->put($this->suiteUrl("hr/overtime/{$second->id}"), ['approve' => true])->assertSessionHasErrors('hours');
        $this->assertSame('pending', $second->fresh()->status);
    }

    public function test_the_app_asks_for_overtime_and_withdraws_it(): void
    {
        Sanctum::actingAs($this->login);

        $id = $this->postJson('/api/v1/overtime', ['work_date' => '2026-11-09', 'hours' => '2.5'])->assertCreated()->assertJsonPath('status', 'pending')->json('id');
        $this->getJson('/api/v1/overtime')->assertOk()->assertJsonPath('items.0.hours', '2.50');
        $this->postJson("/api/v1/overtime/{$id}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');
    }

    public function test_a_loan_is_a_longer_advance_with_an_installment_cap(): void
    {
        // Loan limits: 6 months' wage (30 000), up to 36 installments, each at most 10 % of the wage (500).
        $this->actingAs($this->login)->post('/me/advance', ['kind' => 'loan', 'amount' => '6000', 'installments' => 6, 'first_installment_month' => '2026-12-01'])->assertSessionHasErrors('installments');
        $this->actingAs($this->login)->post('/me/advance', ['kind' => 'loan', 'amount' => '6000', 'installments' => 12, 'first_installment_month' => '2026-12-01'])->assertSessionHasNoErrors();

        // An advance can still be open next to the loan, but not a second loan.
        $this->actingAs($this->login)->post('/me/advance', ['amount' => '1000', 'installments' => 2, 'first_installment_month' => '2026-12-01'])->assertSessionHasNoErrors();
        $this->actingAs($this->login)->post('/me/advance', ['kind' => 'loan', 'amount' => '1000', 'installments' => 2, 'first_installment_month' => '2026-12-01'])->assertSessionHasErrors('amount');

        $this->assertSame(['advance', 'loan'], Advance::query()->orderBy('kind')->pluck('kind')->all());
        $this->actingAs($this->login)->get('/me')->assertInertia(fn (Assert $page) => $page
            ->where('advance.open', true)->where('advance.loan.open', true)->where('advance.loan.max_amount', '30000.00')->where('advance.loan.max_installment', '500.00'));
    }
}
