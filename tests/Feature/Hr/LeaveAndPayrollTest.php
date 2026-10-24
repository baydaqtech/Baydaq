<?php

namespace Tests\Feature\Hr;

use App\Modules\Hr\Actions\Contracts\SaveContract;
use App\Modules\Hr\Actions\Leave\DecideLeave;
use App\Modules\Hr\Actions\Leave\LeaveBalance;
use App\Modules\Hr\Actions\Leave\RequestLeave;
use App\Modules\Hr\Actions\Pay\DecideAdjustment;
use App\Modules\Hr\Actions\Pay\DecideAdvance;
use App\Modules\Hr\Actions\Pay\RequestAdvance;
use App\Modules\Hr\Actions\Pay\SaveBonus;
use App\Modules\Hr\Actions\Pay\SaveDeduction;
use App\Modules\Hr\Actions\Payroll\ApprovePayrollRun;
use App\Modules\Hr\Actions\Payroll\BuildPayrollRun;
use App\Modules\Hr\Actions\Payroll\EndOfServiceBenefit;
use App\Modules\Hr\Actions\Payroll\WpsFile;
use App\Modules\Hr\Enums\LeaveStatus;
use App\Modules\Hr\Models\AllowanceType;
use App\Modules\Hr\Models\BonusType;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\DeductionType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class LeaveAndPayrollTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private Employee $saudi;

    private Employee $expat;

    private LeaveType $annual;

    private LeaveType $unpaid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSuite();
        $this->travelTo('2026-11-15');

        $housing = AllowanceType::factory()->create(['client_id' => $this->client->id]); // 25% of basic, counts for GOSI
        $transport = AllowanceType::factory()->fixed('500.00')->create(['client_id' => $this->client->id]);

        $this->saudi = $this->employeeAt(attributes: ['nationality' => 'SA', 'hired_on' => '2024-01-01', 'iban' => 'SA0380000000608010167519']);
        $this->expat = $this->employeeAt(attributes: ['nationality' => 'EG', 'hired_on' => '2020-03-01']);

        foreach ([$this->saudi, $this->expat] as $employee) {
            app(SaveContract::class)->handle($employee, new Contract, ['type' => 'open_ended', 'status' => 'active', 'starts_on' => '2026-01-01', 'basic_salary' => '8000'], [
                ['allowance_type_id' => $housing->id, 'calculation' => 'percent_of_basic', 'value' => '25'],
                ['allowance_type_id' => $transport->id, 'calculation' => 'fixed', 'value' => '500'],
            ]);
        }

        $this->annual = (new LeaveType(['name_ar' => 'سنوية', 'name_en' => 'Annual', 'days_per_year' => '21', 'senior_days_per_year' => '30', 'senior_after_years' => 5]))->forceFill(['client_id' => $this->client->id]);
        $this->annual->save();
        $this->unpaid = (new LeaveType(['name_ar' => 'بدون راتب', 'name_en' => 'Unpaid', 'days_per_year' => '0', 'is_paid' => false, 'needs_balance' => false]))->forceFill(['client_id' => $this->client->id]);
        $this->unpaid->save();
    }

    public function test_leave_balance_accrues_monthly_carries_over_and_rises_after_five_years(): void
    {
        // Hired 2024: 21 days in 2024 and 2025 carried, then 21 ÷ 12 × 10 completed months (Jan–Oct).
        $saudi = app(LeaveBalance::class)->handle($this->saudi, $this->annual);
        $this->assertSame(['42.00', '17.50', '59.50', '21.00', 10], [$saudi['carried'], $saudi['accrued'], $saudi['entitled'], $saudi['rate'], $saudi['units']]);

        // Hired March 2020: 30 days a year from March 2025 (five years of service).
        $expat = app(LeaveBalance::class)->handle($this->expat, $this->annual);
        $this->assertSame(['25.00', '30.00', '2025-03-01'], [$expat['accrued'], $expat['rate'], $expat['senior_from']]);
        $this->assertSame('130.00', $expat['carried']); // 17.50 (2020) + 4 × 21 + 28.50 (2025: two months at 21, ten at 30)
    }

    public function test_leave_requests_respect_balance_and_overlaps(): void
    {
        $this->annual->forceFill(['carry_over_days' => 0])->save(); // nothing rolls over: only this year's 17.50
        $leave = app(RequestLeave::class)->handle($this->saudi, $this->annual, ['starts_on' => '2026-11-20', 'ends_on' => '2026-11-24'], $this->owner);
        $this->assertSame('5.00', (string) $leave->days);

        app(DecideLeave::class)->handle($leave, LeaveStatus::Approved, null, $this->owner);
        $this->assertSame('12.50', app(LeaveBalance::class)->handle($this->saudi, $this->annual)['available']);

        try {
            app(RequestLeave::class)->handle($this->saudi, $this->annual, ['starts_on' => '2026-11-23', 'ends_on' => '2026-11-25']);
            $this->fail('Overlap accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('starts_on', $e->errors());
        }

        $this->expectException(ValidationException::class);
        app(RequestLeave::class)->handle($this->saudi, $this->annual, ['starts_on' => '2026-12-01', 'ends_on' => '2026-12-20']);
    }

    public function test_a_payroll_run_combines_contract_adjustments_advances_unpaid_leave_and_gosi(): void
    {
        $bonusType = (new BonusType(['name_ar' => 'أداء', 'name_en' => 'Performance']))->forceFill(['client_id' => $this->client->id]);
        $bonusType->save();
        $deductionType = (new DeductionType(['name_ar' => 'تأخير', 'name_en' => 'Lateness']))->forceFill(['client_id' => $this->client->id]);
        $deductionType->save();

        $bonus = app(SaveBonus::class)->handle($this->saudi, $bonusType, ['amount' => '1000', 'pay_month' => '2026-11-01']);
        app(DecideAdjustment::class)->handle($bonus, true, $this->owner);
        $deduction = app(SaveDeduction::class)->handle($this->saudi, $deductionType, ['amount' => '200', 'pay_month' => '2026-11-01']);
        app(DecideAdjustment::class)->handle($deduction, true, $this->owner);
        app(SaveDeduction::class)->handle($this->saudi, $deductionType, ['amount' => '100', 'pay_month' => '2026-11-01']); // pending: must not count

        $advance = app(RequestAdvance::class)->handle($this->saudi, ['amount' => '3000', 'installments' => 3, 'first_installment_month' => '2026-11-01']);
        app(DecideAdvance::class)->approve($advance, $this->owner);
        app(DecideAdvance::class)->disburse($advance->fresh(), 'cash', $this->owner);

        $leave = app(RequestLeave::class)->handle($this->expat, $this->unpaid, ['starts_on' => '2026-11-28', 'ends_on' => '2026-12-02']); // 3 days in November
        app(DecideLeave::class)->handle($leave, LeaveStatus::Approved, null, $this->owner);

        $run = app(BuildPayrollRun::class)->handle($this->company->id, '2026-11-01', $this->owner);

        $this->assertSame(2, $run->employees_count);
        $saudi = $run->lines->firstWhere('employee_id', $this->saudi->id);
        $expat = $run->lines->firstWhere('employee_id', $this->expat->id);

        // Saudi: basic 8000 + housing 2000 + transport 500 + bonus 1000 = 11500 gross.
        // GOSI wage 10000 × 9.75% = 975 employee, × 11.75% = 1175 employer. Deductions 200, installment 1000.
        $this->assertSame('11500.00', (string) $saudi->gross);
        $this->assertSame('975.00', (string) $saudi->gosi_employee);
        $this->assertSame('1175.00', (string) $saudi->gosi_employer);
        $this->assertSame('1000.00', (string) $saudi->advance_installments);
        $this->assertSame('200.00', (string) $saudi->deductions);
        $this->assertSame('9325.00', (string) $saudi->net);

        // Expat: 10500 gross, no employee GOSI, employer 2% of 10000 = 200, 3 unpaid days × 350 = 1050.
        $this->assertSame('10500.00', (string) $expat->gross);
        $this->assertSame('0.00', (string) $expat->gosi_employee);
        $this->assertSame('200.00', (string) $expat->gosi_employer);
        $this->assertSame('1050.00', (string) $expat->unpaid_leave);
        $this->assertSame('9450.00', (string) $expat->net);

        // A draft can be rebuilt; nothing has been marked paid yet.
        app(BuildPayrollRun::class)->handle($this->company->id, '2026-11-01');
        $this->assertSame('approved', $bonus->fresh()->status->value);
        $this->assertSame('3000.00', $advance->fresh()->balance());

        $run = app(ApprovePayrollRun::class)->approve($run->fresh(), $this->owner);
        $this->assertSame('paid', $bonus->fresh()->status->value);
        $this->assertSame('paid', $deduction->fresh()->status->value);
        $this->assertSame('2000.00', $advance->fresh()->balance());
        $this->assertSame('paid', $advance->fresh()->schedule()->first()->status);

        $this->expectException(ValidationException::class);
        app(BuildPayrollRun::class)->handle($this->company->id, '2026-11-01');
    }

    public function test_the_wps_file_lists_each_line_with_the_bank_account(): void
    {
        $run = app(BuildPayrollRun::class)->handle($this->company->id, '2026-11-01');
        app(ApprovePayrollRun::class)->approve($run, $this->owner);
        app(ApprovePayrollRun::class)->markPaid($run->fresh(), '2026-11-28', $this->owner);

        $csv = app(WpsFile::class)->handle($run->fresh());
        $lines = array_filter(explode("\n", trim($csv)));

        $this->assertCount(3, $lines);
        $this->assertStringContainsString('SA0380000000608010167519', $csv);
        $this->assertStringContainsString('2026-11', $csv);
    }

    public function test_end_of_service_follows_articles_84_and_85(): void
    {
        // Expat: hired 2020-03-01, wage 10500. Resigning after ~6.7 years → two thirds of (2.5 + 1.71) months.
        $this->travelTo('2026-11-15');
        $resign = app(EndOfServiceBenefit::class)->handle($this->expat, 'resignation');
        $terminate = app(EndOfServiceBenefit::class)->handle($this->expat, 'termination');

        $this->assertSame('0.666667', $resign['fraction']);
        $this->assertSame('1', $terminate['fraction']);
        $this->assertEqualsWithDelta(6.71, $resign['years'], 0.01);
        $this->assertEqualsWithDelta(10500 * (2.5 + 1.71), (float) $terminate['full_award'], 60);
        $this->assertEqualsWithDelta((float) $terminate['full_award'] * 2 / 3, (float) $resign['award'], 1);

        // Saudi hired 2024-01-01: 2.87 years → a third on resignation, half a month per year in full otherwise.
        $this->assertSame('0.333333', app(EndOfServiceBenefit::class)->handle($this->saudi, 'resignation')['fraction']);
        $this->assertEqualsWithDelta(10500 * 0.5 * 2.87, (float) app(EndOfServiceBenefit::class)->handle($this->saudi, 'contract_end')['award'], 60);
    }
}
