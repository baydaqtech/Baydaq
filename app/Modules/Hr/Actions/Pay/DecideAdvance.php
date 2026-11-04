<?php

namespace App\Modules\Hr\Actions\Pay;

use App\Modules\Hr\Enums\AdvanceStatus;
use App\Modules\Hr\Models\Advance;
use App\Modules\Hr\Support\EmployeeMessenger;
use App\Modules\Platform\Data\LedgerPosting;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\Ledger;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DecideAdvance
{
    public function __construct(private Audit $audit, private EmployeeMessenger $messenger, private Ledger $ledger) {}

    public function approve(Advance $advance, Authenticatable $actor): Advance
    {
        $this->expect($advance, AdvanceStatus::Pending);
        $advance->forceFill(['status' => AdvanceStatus::Approved->value, 'approved_by' => $actor->getAuthIdentifier()])->save();
        $this->audit->record($advance, 'approved', null, $actor);
        $this->tell($advance);

        return $advance;
    }

    public function reject(Advance $advance, Authenticatable $actor): Advance
    {
        $this->expect($advance, AdvanceStatus::Pending);
        $advance->forceFill(['status' => AdvanceStatus::Rejected->value, 'approved_by' => $actor->getAuthIdentifier()])->save();
        $this->audit->record($advance, 'rejected', null, $actor);
        $this->tell($advance);

        return $advance;
    }

    /**
     * The money went out (cash, transfer, or it will ride on the next payroll). Repayment starts
     * after this. In the books the employee now owes it: due from employees against cash or bank
     * (a payroll payout leaves with the salary transfer).
     */
    public function disburse(Advance $advance, string $method, Authenticatable $actor): Advance
    {
        return DB::transaction(function () use ($advance, $method, $actor): Advance {
            $advance = Advance::query()->lockForUpdate()->with('employee')->findOrFail($advance->id);
            $this->expect($advance, AdvanceStatus::Approved);
            $advance->forceFill(['status' => AdvanceStatus::Disbursed->value, 'disbursement' => $method, 'disbursed_on' => today()->toDateString()])->save();
            $this->audit->record($advance, 'disbursed', ['method' => $method], $actor);

            $this->ledger->post(new LedgerPosting($advance->employee->company_id, $advance->employee->branch_id, $advance->disbursed_on->toDateString(), 'hr.advance', $advance->id, "Advance {$advance->employee->employee_number}", [
                ['account' => 'employee_receivable', 'debit' => (string) $advance->amount],
                ['account' => $method === 'cash' ? 'cash' : 'bank', 'credit' => (string) $advance->amount],
            ]));

            return $advance;
        });
    }

    private function tell(Advance $advance): void
    {
        $this->messenger->send($advance->employee, 'hr_advance_decided', ['amount' => (string) $advance->amount, 'status' => __("hr.advance_status.{$advance->status->value}")], "hr.advance:{$advance->id}");
    }

    private function expect(Advance $advance, AdvanceStatus $status): void
    {
        if ($advance->status !== $status) {
            throw ValidationException::withMessages(['status' => __('hr.pay.errors.advance_status')]);
        }
    }
}
