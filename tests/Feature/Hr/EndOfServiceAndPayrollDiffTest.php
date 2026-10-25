<?php

namespace Tests\Feature\Hr;

use App\Modules\Hr\Actions\Contracts\SaveContract;
use App\Modules\Hr\Actions\Employees\ChangeEmployeeStatus;
use App\Modules\Hr\Actions\Pay\DecideAdvance;
use App\Modules\Hr\Actions\Pay\RequestAdvance;
use App\Modules\Hr\Actions\Pay\SaveBonus;
use App\Modules\Hr\Actions\Payroll\ApprovePayrollRun;
use App\Modules\Hr\Actions\Payroll\BuildPayrollRun;
use App\Modules\Hr\Actions\Payroll\PayrollDiff;
use App\Modules\Hr\Actions\Payroll\SettleFinalAccount;
use App\Modules\Hr\Enums\AdvanceStatus;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Models\BonusType;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\FinalSettlement;
use App\Modules\Platform\Enums\ScopeLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class EndOfServiceAndPayrollDiffTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private Employee $long;

    private Employee $short;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSuite();
        $this->travelTo('2026-10-15');

        // 10 years at 10000/month → 5 × 0.5 + 5 × 1 = 7.5 months = 75000 in full.
        $this->long = $this->employeeAt(attributes: ['nationality' => 'EG', 'hired_on' => '2016-10-15']);
        $this->short = $this->employeeAt($this->branch('JED'), ['nationality' => 'EG', 'hired_on' => '2025-10-15']);

        foreach ([$this->long, $this->short] as $employee) {
            app(SaveContract::class)->handle($employee, new Contract, ['type' => 'open_ended', 'status' => 'active', 'starts_on' => '2026-01-01', 'basic_salary' => '10000'], []);
        }
    }

    public function test_the_final_settlement_takes_open_advances_out_of_the_award(): void
    {
        $advance = app(RequestAdvance::class)->handle($this->long, ['amount' => '6000', 'installments' => 6, 'first_installment_month' => '2026-11-01']);
        app(DecideAdvance::class)->approve($advance, $this->owner);
        app(DecideAdvance::class)->disburse($advance->fresh(), 'cash', $this->owner);

        $preview = app(SettleFinalAccount::class)->preview($this->long, 'termination');
        $this->assertSame('6000.00', $preview['advances_owed']);
        $this->assertSame(bcsub($preview['eosb']['award'], '6000', 2), $preview['net_payable']);

        app(ChangeEmployeeStatus::class)->handle($this->long, EmployeeStatus::Terminated, $this->owner);
        $this->actingAs($this->owner)->post($this->suiteUrl("hr/employees/{$this->long->id}/final-settlement"), ['reason' => 'termination'])->assertSessionHasNoErrors();

        $settlement = FinalSettlement::query()->where('employee_id', $this->long->id)->sole();
        $this->assertSame('6000.00', (string) $settlement->advances_recovered);
        $this->assertSame('0.00', (string) $settlement->advances_left);
        $this->assertSame(AdvanceStatus::Settled, $advance->fresh()->status);

        $this->expectException(ValidationException::class);
        app(SettleFinalAccount::class)->settle($this->long->fresh(), 'termination', $this->owner);
    }

    public function test_an_award_smaller_than_the_advance_leaves_the_rest_to_collect(): void
    {
        // Under two years and resigning: no award at all.
        $advance = app(RequestAdvance::class)->handle($this->short, ['amount' => '2000', 'installments' => 2, 'first_installment_month' => '2026-11-01']);
        app(DecideAdvance::class)->approve($advance, $this->owner);
        app(DecideAdvance::class)->disburse($advance->fresh(), 'cash', $this->owner);
        app(ChangeEmployeeStatus::class)->handle($this->short, EmployeeStatus::Terminated, $this->owner);

        $settlement = app(SettleFinalAccount::class)->settle($this->short->fresh(), 'resignation', $this->owner);

        $this->assertSame('0.00', (string) $settlement->net_payable);
        $this->assertSame('2000.00', (string) $settlement->advances_left);
        $this->assertSame(AdvanceStatus::Disbursed, $advance->fresh()->status);
    }

    public function test_settling_someone_still_employed_is_refused(): void
    {
        $this->expectException(ValidationException::class);
        app(SettleFinalAccount::class)->settle($this->long, 'termination', $this->owner);
    }

    public function test_the_liability_report_sums_full_awards_within_reach(): void
    {
        $this->actingAs($this->owner)->get($this->suiteUrl('hr/reports/eosb'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Hr/Reports/EosbLiability')
                // 75000 + one year at half a month (5000); leap days add a few riyals.
                ->where('total', fn (string $total): bool => abs((float) $total - 80000) < 100)
                ->has('employees', 2)
                ->where('employees.0.id', $this->long->id));

        $jeddahOnly = $this->memberWith(['hr.payroll.view_eosb'], ScopeLevel::Branch, $this->branch('JED'));
        $this->actingAs($jeddahOnly)->get($this->suiteUrl('hr/reports/eosb'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('total', fn (string $total): bool => abs((float) $total - 5000) < 20)->has('employees', 1));

        $noKey = $this->memberWith(['hr.employees.view'], ScopeLevel::Client);
        $this->actingAs($noKey)->get($this->suiteUrl('hr/reports/eosb'))->assertForbidden();
    }

    public function test_the_payroll_diff_lists_joiners_leavers_and_moved_nets(): void
    {
        $first = app(BuildPayrollRun::class)->handle($this->company->id, '2026-10-01', $this->owner);
        $this->assertNull(app(PayrollDiff::class)->handle($first));
        app(ApprovePayrollRun::class)->approve($first, $this->owner);

        $this->travelTo('2026-11-10');
        $joiner = $this->employeeAt(attributes: ['nationality' => 'EG', 'hired_on' => '2026-11-01']);
        app(SaveContract::class)->handle($joiner, new Contract, ['type' => 'open_ended', 'status' => 'active', 'starts_on' => '2026-11-01', 'basic_salary' => '4000'], []);
        app(ChangeEmployeeStatus::class)->handle($this->short, EmployeeStatus::Terminated, $this->owner, '2026-10-31');
        $type = (new BonusType(['name_ar' => 'أداء', 'name_en' => 'Performance']))->forceFill(['client_id' => $this->client->id]);
        $type->save();
        $bonus = app(SaveBonus::class)->handle($this->long, $type, ['amount' => '1500', 'pay_month' => '2026-11-01']);
        $bonus->forceFill(['status' => 'approved'])->save();

        $second = app(BuildPayrollRun::class)->handle($this->company->id, '2026-11-01', $this->owner);
        $diff = app(PayrollDiff::class)->handle($second);

        $this->assertSame('2026-10', $diff['previous_month']);
        $this->assertSame([$joiner->id], array_column($diff['joined'], 'employee_id'));
        $this->assertSame([$this->short->id], array_column($diff['left'], 'employee_id'));
        $this->assertSame($this->long->id, $diff['changed'][0]['employee_id']);
        $this->assertSame('1500.00', $diff['changed'][0]['parts']['bonuses']);

        $this->actingAs($this->owner)->get($this->suiteUrl("hr/payroll/{$second->id}"))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('diff.previous_month', '2026-10'));
    }
}
