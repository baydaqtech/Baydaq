<?php

namespace App\Modules\Hr\Actions\Pay;

use App\Modules\Hr\Enums\AdvanceStatus;
use App\Modules\Hr\Enums\RepaymentMethod;
use App\Modules\Hr\Models\Advance;
use App\Modules\Hr\Models\AdvanceInstallment;
use App\Modules\Platform\Ports\Audit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordRepayment
{
    public function __construct(private Audit $audit) {}

    /**
     * Money back on an advance. It fills the due installments oldest first (or one named
     * installment, for payroll); a settlement pays whatever is left. Zero balance settles the advance.
     */
    public function handle(Advance $advance, string $amount, RepaymentMethod $method, ?Authenticatable $actor = null, ?AdvanceInstallment $installment = null, ?int $payrollLineId = null, ?string $reference = null): Advance
    {
        if ($advance->status !== AdvanceStatus::Disbursed) {
            throw ValidationException::withMessages(['amount' => __('hr.pay.errors.advance_not_disbursed')]);
        }

        $balance = BigDecimal::of($advance->balance());
        $paying = $method === RepaymentMethod::Settlement ? $balance : BigDecimal::of($amount)->toScale(2, RoundingMode::HalfUp);

        if (! $paying->isPositive() || $paying->isGreaterThan($balance)) {
            throw ValidationException::withMessages(['amount' => __('hr.pay.errors.repayment_amount', ['balance' => (string) $balance])]);
        }

        return DB::transaction(function () use ($advance, $paying, $method, $actor, $installment, $payrollLineId, $reference): Advance {
            $left = $paying;
            $targets = $installment ? collect([$installment]) : $advance->schedule()->where('status', 'due')->get();

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

            $advance->repayments()->make()->forceFill([
                'installment_id' => $installment?->id,
                'amount' => (string) $paying,
                'method' => $method->value,
                'paid_on' => today()->toDateString(),
                'reference' => $reference,
                'recorded_by' => $actor?->getAuthIdentifier(),
            ])->save();

            if (BigDecimal::of($advance->balance())->isZero()) {
                $advance->forceFill(['status' => AdvanceStatus::Settled->value])->save();
                $advance->schedule()->where('status', 'due')->update(['status' => 'skipped']);
            }

            $this->audit->record($advance, 'repaid', ['amount' => (string) $paying, 'method' => $method->value], $actor);

            return $advance->refresh()->load(['schedule', 'repayments']);
        });
    }
}
