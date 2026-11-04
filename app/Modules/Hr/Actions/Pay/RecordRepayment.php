<?php

namespace App\Modules\Hr\Actions\Pay;

use App\Modules\Hr\Enums\AdvanceStatus;
use App\Modules\Hr\Enums\RepaymentMethod;
use App\Modules\Hr\Models\Advance;
use App\Modules\Hr\Models\AdvanceInstallment;
use App\Modules\Platform\Data\LedgerPosting;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\Ledger;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordRepayment
{
    /** Repayments made with money, and where it lands. */
    private const MONEY_IN = ['cash' => 'cash', 'transfer' => 'bank', 'settlement' => 'bank'];

    public function __construct(private Audit $audit, private Ledger $ledger) {}

    /**
     * Money back on an advance. It fills the due installments oldest first (or one named
     * installment, for payroll); a settlement pays whatever is left. Zero balance settles the advance.
     *
     * Two repayments at once (cash at the desk while payroll is approved) must not both fit the
     * same balance: the advance is locked first and its status, balance and installments are read
     * with locking reads, which see the latest commits even inside a longer payroll transaction.
     */
    public function handle(Advance $advance, string $amount, RepaymentMethod $method, ?Authenticatable $actor = null, ?AdvanceInstallment $installment = null, ?int $payrollLineId = null, ?string $reference = null): Advance
    {
        return DB::transaction(function () use ($advance, $amount, $method, $actor, $installment, $payrollLineId, $reference): Advance {
            $advance = Advance::query()->lockForUpdate()->findOrFail($advance->id);

            if ($advance->status !== AdvanceStatus::Disbursed) {
                throw ValidationException::withMessages(['amount' => __('hr.pay.errors.advance_not_disbursed')]);
            }

            $balance = BigDecimal::of((string) $advance->amount)->minus((string) $advance->repayments()->sharedLock()->sum('amount'));
            $paying = $method === RepaymentMethod::Settlement ? $balance : BigDecimal::of($amount)->toScale(2, RoundingMode::HalfUp);

            if (! $paying->isPositive() || $paying->isGreaterThan($balance)) {
                throw ValidationException::withMessages(['amount' => __('hr.pay.errors.repayment_amount', ['balance' => (string) $balance->toScale(2)])]);
            }

            $left = $paying;
            $targets = $installment
                ? AdvanceInstallment::query()->whereKey($installment->id)->lockForUpdate()->get()
                : $advance->schedule()->where('status', 'due')->lockForUpdate()->get();

            foreach ($targets as $target) {
                if (! $left->isPositive()) {
                    break;
                }

                $portion = BigDecimal::min($left, BigDecimal::of($target->remaining()));
                $target->forceFill([
                    'paid' => (string) BigDecimal::of((string) $target->paid)->plus($portion),
                    'status' => BigDecimal::of((string) $target->paid)->plus($portion)->isGreaterThanOrEqualTo((string) $target->amount) ? 'paid' : 'due',
                    'payroll_line_id' => $payrollLineId ?? $target->payroll_line_id,
                ])->save();
                $left = $left->minus($portion);
            }

            $repayment = $advance->repayments()->make()->forceFill([
                'installment_id' => $installment?->id,
                'amount' => (string) $paying,
                'method' => $method->value,
                'paid_on' => today()->toDateString(),
                'reference' => $reference,
                'recorded_by' => $actor?->getAuthIdentifier(),
            ]);
            $repayment->save();

            // Money handed back clears what the employee owes; payroll and end-of-service
            // repayments are in the books with the payroll and the final settlement.
            if (isset(self::MONEY_IN[$method->value])) {
                $advance->loadMissing('employee');
                $this->ledger->post(new LedgerPosting($advance->employee->company_id, $advance->employee->branch_id, $repayment->paid_on->toDateString(), 'hr.advance_repayment', $repayment->id, "Advance repaid {$advance->employee->employee_number}", [
                    ['account' => self::MONEY_IN[$method->value], 'debit' => (string) $paying],
                    ['account' => 'employee_receivable', 'credit' => (string) $paying],
                ]));
            }

            if ($balance->minus($paying)->isZero()) {
                $advance->forceFill(['status' => AdvanceStatus::Settled->value])->save();
                $advance->schedule()->where('status', 'due')->update(['status' => 'skipped']);
            }

            $this->audit->record($advance, 'repaid', ['amount' => (string) $paying, 'method' => $method->value], $actor);

            return $advance->refresh()->load(['schedule', 'repayments']);
        });
    }
}
