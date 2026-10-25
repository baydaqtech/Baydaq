<?php

namespace App\Modules\Hr\Actions\Payroll;

use App\Modules\Hr\Actions\Pay\RecordRepayment;
use App\Modules\Hr\Enums\AdjustmentStatus;
use App\Modules\Hr\Enums\AdvanceStatus;
use App\Modules\Hr\Enums\PayrollStatus;
use App\Modules\Hr\Enums\RepaymentMethod;
use App\Modules\Hr\Models\Advance;
use App\Modules\Hr\Models\Bonus;
use App\Modules\Hr\Models\Deduction;
use App\Modules\Hr\Models\PayrollRun;
use App\Modules\Hr\Support\EmployeeMessenger;
use App\Modules\Platform\Data\LedgerPosting;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\Ledger;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovePayrollRun
{
    public function __construct(private Audit $audit, private RecordRepayment $repay, private Ledger $ledger) {}

    /**
     * Freeze the run: the bonuses and deductions it carries become paid and the advance
     * installments it deducted are recorded as repayments. From here the run can only be marked paid.
     */
    public function approve(PayrollRun $run, Authenticatable $actor): PayrollRun
    {
        if ($run->status !== PayrollStatus::Draft) {
            throw ValidationException::withMessages(['status' => __('hr.payroll.errors.already_approved')]);
        }

        if ($run->lines()->doesntExist()) {
            throw ValidationException::withMessages(['status' => __('hr.payroll.errors.empty')]);
        }

        return DB::transaction(function () use ($run, $actor): PayrollRun {
            foreach ($run->lines()->get() as $line) {
                Bonus::query()->where('employee_id', $line->employee_id)->whereDate('pay_month', $run->pay_month)->where('status', AdjustmentStatus::Approved)
                    ->update(['status' => AdjustmentStatus::Paid->value, 'payroll_line_id' => $line->id]);
                Deduction::query()->where('employee_id', $line->employee_id)->whereDate('pay_month', $run->pay_month)->where('status', AdjustmentStatus::Approved)
                    ->update(['status' => AdjustmentStatus::Paid->value, 'payroll_line_id' => $line->id]);

                $advances = Advance::query()->where('employee_id', $line->employee_id)->where('status', AdvanceStatus::Disbursed)
                    ->with(['schedule' => fn ($query) => $query->where('status', 'due')->whereDate('due_month', '<=', $run->pay_month)])->get();

                foreach ($advances as $advance) {
                    foreach ($advance->schedule as $installment) {
                        $this->repay->handle($advance, $installment->remaining(), RepaymentMethod::Payroll, $actor, $installment, $line->id);
                    }
                }
            }

            $run->forceFill(['status' => PayrollStatus::Approved->value, 'approved_by' => $actor->getAuthIdentifier(), 'approved_at' => now()])->save();
            $this->audit->record($run, 'approved', ['employees' => $run->employees_count, 'net' => (string) $run->total_net], $actor);
            $this->ledger->post(self::posting($run));

            return $run;
        });
    }

    /**
     * The month's payroll for the books: salaries and the employer's GOSI as expenses; net pay,
     * GOSI (both shares) and other withholdings (penalties, staff meals, advances) as owed.
     */
    public static function posting(PayrollRun $run): LedgerPosting
    {
        $sum = fn (string $column): BigDecimal => BigDecimal::of((string) $run->lines()->sum($column));
        [$net, $gosiEmployee, $gosiEmployer] = [$sum('net'), $sum('gosi_employee'), $sum('gosi_employer')];
        $earned = $sum('gross')->minus($sum('unpaid_leave')); // unpaid leave was never earned

        return new LedgerPosting($run->company_id, null, $run->pay_month->copy()->endOfMonth()->toDateString(), 'hr.payroll', $run->id, 'Payroll '.$run->pay_month->format('Y-m'), [
            ['account' => 'salaries', 'debit' => (string) $earned->toScale(2)],
            ['account' => 'gosi_expense', 'debit' => (string) $gosiEmployer->toScale(2)],
            ['account' => 'salaries_payable', 'credit' => (string) $net->toScale(2)],
            ['account' => 'gosi_payable', 'credit' => (string) $gosiEmployee->plus($gosiEmployer)->toScale(2)],
            ['account' => 'payroll_withholdings', 'credit' => (string) $earned->minus($net)->minus($gosiEmployee)->toScale(2)],
        ]);
    }

    public function markPaid(PayrollRun $run, string $paidOn, Authenticatable $actor): PayrollRun
    {
        if ($run->status !== PayrollStatus::Approved) {
            throw ValidationException::withMessages(['status' => __('hr.payroll.errors.not_approved')]);
        }

        $run->forceFill(['status' => PayrollStatus::Paid->value, 'paid_on' => $paidOn])->save();
        $this->ledger->post(new LedgerPosting($run->company_id, null, $paidOn, 'hr.payroll_payment', $run->id, 'Salaries paid '.$run->pay_month->format('Y-m'), [
            ['account' => 'salaries_payable', 'debit' => (string) BigDecimal::of((string) $run->lines()->sum('net'))->toScale(2)],
            ['account' => 'bank', 'credit' => (string) BigDecimal::of((string) $run->lines()->sum('net'))->toScale(2)],
        ]));
        $this->audit->record($run, 'paid', ['paid_on' => $paidOn], $actor);

        foreach ($run->lines()->with('employee')->get() as $line) {
            app(EmployeeMessenger::class)->send($line->employee, 'hr_payslip_ready', ['month' => $run->pay_month->format('Y-m'), 'amount' => (string) $line->net], "hr.payroll_line:{$line->id}");
        }

        return $run;
    }
}
