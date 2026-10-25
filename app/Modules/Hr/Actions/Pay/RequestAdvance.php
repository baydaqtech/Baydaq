<?php

namespace App\Modules\Hr\Actions\Pay;

use App\Modules\Hr\Enums\AdvanceStatus;
use App\Modules\Hr\Models\Advance;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Ports\Audit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestAdvance
{
    /** Default owner policy until a settings page exists: up to 3 months' wage, one open advance. */
    public const MAX_MONTHS = 3;

    /** Longest repayment plan an employee can ask for themselves. */
    public const MAX_INSTALLMENTS = 12;

    public function __construct(private Audit $audit, private DailyWage $wage) {}

    /**
     * Ask for an advance and lay out its installments (equal amounts, remainder on the last one).
     *
     * @param  array{amount: numeric-string|float|int, installments: int, first_installment_month: string, reason?: ?string}  $data
     */
    public function handle(Employee $employee, array $data, ?Authenticatable $actor = null): Advance
    {
        $amount = BigDecimal::of((string) $data['amount'])->toScale(2, RoundingMode::HalfUp);
        $monthly = $this->wage->monthly($this->wage->contractOf($employee));

        if ($monthly->isPositive() && $amount->isGreaterThan($monthly->multipliedBy(self::MAX_MONTHS))) {
            throw ValidationException::withMessages(['amount' => __('hr.pay.errors.advance_too_large', ['months' => self::MAX_MONTHS])]);
        }

        if (Advance::query()->where('employee_id', $employee->id)->whereIn('status', [AdvanceStatus::Pending, AdvanceStatus::Approved, AdvanceStatus::Disbursed])->exists()) {
            throw ValidationException::withMessages(['amount' => __('hr.pay.errors.advance_open')]);
        }

        $count = max(1, (int) $data['installments']);
        $first = Carbon::parse($data['first_installment_month'])->startOfMonth();

        return DB::transaction(function () use ($employee, $data, $amount, $count, $first, $actor): Advance {
            $advance = new Advance(['amount' => (string) $amount, 'installments' => $count, 'first_installment_month' => $first->toDateString(), 'reason' => $data['reason'] ?? null]);
            $advance->forceFill(['client_id' => $employee->client_id, 'employee_id' => $employee->id, 'status' => AdvanceStatus::Pending->value, 'requested_by' => $actor?->getAuthIdentifier()])->save();

            foreach (self::schedule($amount, $count, $first) as $line) {
                $advance->schedule()->make()->forceFill($line)->save();
            }

            $this->audit->record($advance, 'requested', ['amount' => (string) $amount, 'installments' => $count], $actor);

            return $advance->load('schedule');
        });
    }

    /**
     * Equal installments rounded down to the halala, the remainder on the last one.
     *
     * @return list<array{due_month: string, amount: string}>
     */
    public static function schedule(BigDecimal $amount, int $count, Carbon $first): array
    {
        $each = $amount->dividedBy($count, 2, RoundingMode::Down);
        $remainder = $amount->minus($each->multipliedBy($count));

        return array_map(fn (int $i): array => [
            'due_month' => $first->copy()->addMonths($i)->toDateString(),
            'amount' => (string) ($i === $count - 1 ? $each->plus($remainder) : $each),
        ], range(0, $count - 1));
    }
}
