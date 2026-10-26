<?php

namespace App\Modules\Hr\Actions\Payroll;

use App\Modules\Hr\Actions\Pay\RecordRepayment;
use App\Modules\Hr\Enums\AdvanceStatus;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\RepaymentMethod;
use App\Modules\Hr\Models\Advance;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\FinalSettlement;
use App\Modules\Platform\Ports\Audit;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A leaver's final account: the end-of-service award less whatever is still owed on salary
 * advances. Open advances are recovered from the award first; if the award doesn't cover them,
 * the rest stays on the advance to be collected another way.
 */
class SettleFinalAccount
{
    public function __construct(private EndOfServiceBenefit $eosb, private RecordRepayment $recordRepayment, private Audit $audit) {}

    /**
     * @param  'termination'|'resignation'|'contract_end'|'retirement'  $reason
     * @return array{eosb: array{years: float, monthly_wage: string, full_award: string, fraction: string, award: string}, advances_owed: string, advances_recovered: string, advances_left: string, net_payable: string}
     */
    public function preview(Employee $employee, string $reason, ?Carbon $lastDay = null): array
    {
        $eosb = $this->eosb->handle($employee, $reason, $lastDay);
        $owed = $this->openAdvances($employee)->reduce(fn (BigDecimal $sum, Advance $advance): BigDecimal => $sum->plus($advance->balance()), BigDecimal::zero());
        $recovered = BigDecimal::min($owed, BigDecimal::of($eosb['award']));

        return [
            'eosb' => $eosb,
            'advances_owed' => (string) $owed->toScale(2),
            'advances_recovered' => (string) $recovered->toScale(2),
            'advances_left' => (string) $owed->minus($recovered)->toScale(2),
            'net_payable' => (string) BigDecimal::of($eosb['award'])->minus($recovered)->toScale(2),
        ];
    }

    /**
     * Record the settlement for a terminated employee and take the open advances out of the award.
     *
     * @param  'termination'|'resignation'|'contract_end'|'retirement'  $reason
     */
    public function settle(Employee $employee, string $reason, ?Authenticatable $actor = null): FinalSettlement
    {
        if ($employee->status !== EmployeeStatus::Terminated) {
            throw ValidationException::withMessages(['reason' => __('hr.payroll.errors.not_terminated')]);
        }

        if (FinalSettlement::query()->where('employee_id', $employee->id)->exists()) {
            throw ValidationException::withMessages(['reason' => __('hr.payroll.errors.already_settled')]);
        }

        return DB::transaction(function () use ($employee, $reason, $actor): FinalSettlement {
            $lastDay = $employee->terminated_on ?? today();
            $preview = $this->preview($employee, $reason, $lastDay);
            $budget = BigDecimal::of($preview['advances_recovered']);

            foreach ($this->openAdvances($employee) as $advance) {
                $portion = BigDecimal::min($budget, BigDecimal::of($advance->balance()));

                if ($portion->isPositive()) {
                    $this->recordRepayment->handle($advance, (string) $portion, RepaymentMethod::FinalSettlement, $actor, reference: 'EOSB');
                    $budget = $budget->minus($portion);
                }
            }

            $settlement = (new FinalSettlement)->forceFill([
                'client_id' => $employee->client_id,
                'employee_id' => $employee->id,
                'reason' => $reason,
                'last_day' => $lastDay,
                'eosb_award' => $preview['eosb']['award'],
                'advances_recovered' => $preview['advances_recovered'],
                'advances_left' => $preview['advances_left'],
                'net_payable' => $preview['net_payable'],
                'settled_by' => $actor?->getAuthIdentifier(),
            ]);
            $settlement->save();
            $this->audit->record($employee, 'final_settlement', ['award' => $preview['eosb']['award'], 'recovered' => $preview['advances_recovered'], 'net' => $preview['net_payable']], $actor);

            return $settlement;
        });
    }

    /**
     * @return Collection<int, Advance>
     */
    private function openAdvances(Employee $employee): Collection
    {
        return Advance::query()->where('employee_id', $employee->id)->where('status', AdvanceStatus::Disbursed->value)->oldest()->get()
            ->filter(fn (Advance $advance): bool => BigDecimal::of($advance->balance())->isPositive())->values();
    }
}
