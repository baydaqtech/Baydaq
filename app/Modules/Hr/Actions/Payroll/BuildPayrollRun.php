<?php

namespace App\Modules\Hr\Actions\Payroll;

use App\Modules\Hr\Actions\Pay\DailyWage;
use App\Modules\Hr\Enums\AdjustmentStatus;
use App\Modules\Hr\Enums\AdvanceStatus;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\LeaveStatus;
use App\Modules\Hr\Enums\PayrollStatus;
use App\Modules\Hr\Models\Advance;
use App\Modules\Hr\Models\Bonus;
use App\Modules\Hr\Models\Deduction;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\PayrollRun;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BuildPayrollRun
{
    public function __construct(private DailyWage $wage, private Gosi $gosi, private CurrentTenant $tenant) {}

    /**
     * (Re)build the draft payroll of one company for one month: every seated employee with an
     * active contract gets a line from the contract, plus approved bonuses and deductions of that
     * month, minus due advance installments and unpaid leave days. Nothing is marked as paid until
     * the run is approved, so a draft can be rebuilt freely.
     */
    public function handle(int $companyId, string $month, ?Authenticatable $actor = null): PayrollRun
    {
        $payMonth = Carbon::parse($month)->startOfMonth();
        $run = PayrollRun::query()->where('company_id', $companyId)->whereDate('pay_month', $payMonth)->first();

        if ($run !== null && $run->status !== PayrollStatus::Draft) {
            throw ValidationException::withMessages(['pay_month' => __('hr.payroll.errors.already_approved')]);
        }

        return DB::transaction(function () use ($companyId, $payMonth, $run): PayrollRun {
            $run ??= (new PayrollRun)->forceFill(['client_id' => $this->tenant->client()->id, 'company_id' => $companyId, 'pay_month' => $payMonth->toDateString(), 'status' => PayrollStatus::Draft->value]);
            $run->save();
            $run->lines()->delete();

            $monthEnd = $payMonth->copy()->endOfMonth();
            $totals = ['gross' => BigDecimal::zero(), 'deductions' => BigDecimal::zero(), 'net' => BigDecimal::zero(), 'employer' => BigDecimal::zero()];
            $count = 0;

            $employees = Employee::query()->where('company_id', $companyId)->whereIn('status', EmployeeStatus::seated())->with(['activeContract.allowances.type'])->get();

            foreach ($employees as $employee) {
                $contract = $employee->activeContract;

                if ($contract === null) {
                    continue;
                }

                $basic = BigDecimal::of((string) $contract->basic_salary);
                $allowances = $contract->allowances->reduce(fn (BigDecimal $sum, $line) => $sum->plus((string) $line->amount), BigDecimal::zero());
                $breakdown = [['kind' => 'basic', 'label_ar' => 'الراتب الأساسي', 'label_en' => 'Basic salary', 'amount' => (string) $basic]];

                foreach ($contract->allowances as $line) {
                    $breakdown[] = ['kind' => 'allowance', 'label_ar' => $line->type->name_ar, 'label_en' => $line->type->name_en, 'amount' => (string) $line->amount];
                }

                $bonuses = Bonus::query()->where('employee_id', $employee->id)->whereDate('pay_month', $payMonth)->where('status', AdjustmentStatus::Approved)->with('type')->get();
                $deductions = Deduction::query()->where('employee_id', $employee->id)->whereDate('pay_month', $payMonth)->where('status', AdjustmentStatus::Approved)->with('type')->get();

                foreach ($bonuses as $bonus) {
                    $breakdown[] = ['kind' => 'bonus', 'label_ar' => $bonus->type->name_ar, 'label_en' => $bonus->type->name_en, 'amount' => (string) $bonus->amount];
                }

                foreach ($deductions as $deduction) {
                    $breakdown[] = ['kind' => 'deduction', 'label_ar' => $deduction->type->name_ar, 'label_en' => $deduction->type->name_en, 'amount' => '-'.$deduction->amount];
                }

                $installments = Advance::query()->where('employee_id', $employee->id)->where('status', AdvanceStatus::Disbursed)
                    ->with(['schedule' => fn ($query) => $query->where('status', 'due')->whereDate('due_month', '<=', $payMonth)])->get()
                    ->flatMap->schedule;
                $installmentTotal = $installments->reduce(fn (BigDecimal $sum, $line) => $sum->plus($line->remaining()), BigDecimal::zero());

                if ($installmentTotal->isPositive()) {
                    $breakdown[] = ['kind' => 'advance', 'label_ar' => 'قسط سلفة', 'label_en' => 'Advance installment', 'amount' => '-'.$installmentTotal];
                }

                $unpaidDays = $this->unpaidLeaveDays($employee, $payMonth, $monthEnd);
                $daily = $this->wage->daily($contract);
                $unpaid = $daily->multipliedBy($unpaidDays)->toScale(2, RoundingMode::HalfUp);

                if ($unpaid->isPositive()) {
                    $breakdown[] = ['kind' => 'unpaid_leave', 'label_ar' => "إجازة بدون راتب ({$unpaidDays} يوم)", 'label_en' => "Unpaid leave ({$unpaidDays} days)", 'amount' => '-'.$unpaid];
                }

                $gosi = $this->gosi->handle($employee, $contract);

                if ($gosi['employee']->isPositive()) {
                    $breakdown[] = ['kind' => 'gosi', 'label_ar' => 'التأمينات الاجتماعية', 'label_en' => 'GOSI', 'amount' => '-'.$gosi['employee']];
                }

                $bonusTotal = $bonuses->reduce(fn (BigDecimal $sum, $bonus) => $sum->plus((string) $bonus->amount), BigDecimal::zero());
                $deductionTotal = $deductions->reduce(fn (BigDecimal $sum, $deduction) => $sum->plus((string) $deduction->amount), BigDecimal::zero());
                $gross = $basic->plus($allowances)->plus($bonusTotal);
                $allDeductions = $deductionTotal->plus($installmentTotal)->plus($unpaid)->plus($gosi['employee']);
                $net = $gross->minus($allDeductions);

                $run->lines()->make()->forceFill([
                    'employee_id' => $employee->id,
                    'branch_id' => $employee->branch_id,
                    'basic' => (string) $basic,
                    'allowances' => (string) $allowances,
                    'bonuses' => (string) $bonusTotal,
                    'deductions' => (string) $deductionTotal,
                    'advance_installments' => (string) $installmentTotal,
                    'unpaid_leave' => (string) $unpaid,
                    'unpaid_leave_days' => $unpaidDays,
                    'gosi_employee' => (string) $gosi['employee'],
                    'gosi_employer' => (string) $gosi['employer'],
                    'gross' => (string) $gross->toScale(2),
                    'net' => (string) $net->toScale(2),
                    'breakdown' => $breakdown,
                ])->save();

                $totals['gross'] = $totals['gross']->plus($gross);
                $totals['deductions'] = $totals['deductions']->plus($allDeductions);
                $totals['net'] = $totals['net']->plus($net);
                $totals['employer'] = $totals['employer']->plus($gosi['employer']);
                $count++;
            }

            $run->forceFill([
                'total_gross' => (string) $totals['gross']->toScale(2),
                'total_deductions' => (string) $totals['deductions']->toScale(2),
                'total_net' => (string) $totals['net']->toScale(2),
                'total_employer_gosi' => (string) $totals['employer']->toScale(2),
                'employees_count' => $count,
            ])->save();

            return $run->load('lines');
        });
    }

    /** Days of approved unpaid leave that fall inside the month. */
    private function unpaidLeaveDays(Employee $employee, Carbon $start, Carbon $end): int
    {
        return LeaveRequest::query()->where('employee_id', $employee->id)->where('status', LeaveStatus::Approved)
            ->whereHas('type', fn ($query) => $query->where('is_paid', false))
            ->whereDate('starts_on', '<=', $end)->whereDate('ends_on', '>=', $start)->get()
            ->sum(fn (LeaveRequest $leave): int => (int) max($leave->starts_on, $start)->diffInDays(min($leave->ends_on, $end)) + 1);
    }
}
