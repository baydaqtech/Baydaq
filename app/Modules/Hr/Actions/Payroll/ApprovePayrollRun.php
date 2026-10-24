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
use App\Modules\Platform\Ports\Audit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovePayrollRun
{
    public function __construct(private Audit $audit, private RecordRepayment $repay) {}

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

            return $run;
        });
    }

    public function markPaid(PayrollRun $run, string $paidOn, Authenticatable $actor): PayrollRun
    {
        if ($run->status !== PayrollStatus::Approved) {
            throw ValidationException::withMessages(['status' => __('hr.payroll.errors.not_approved')]);
        }

        $run->forceFill(['status' => PayrollStatus::Paid->value, 'paid_on' => $paidOn])->save();
        $this->audit->record($run, 'paid', ['paid_on' => $paidOn], $actor);

        return $run;
    }
}
