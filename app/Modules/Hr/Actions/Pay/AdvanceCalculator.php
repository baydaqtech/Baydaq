<?php

namespace App\Modules\Hr\Actions\Pay;

use App\Modules\Hr\Enums\AdvanceStatus;
use App\Modules\Hr\Models\Advance;
use App\Modules\Hr\Models\Employee;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;

/**
 * What an employee may ask for before asking (H18): the ceiling from their wage, whether an
 * advance is already open, and the installment plan a given amount would make.
 */
class AdvanceCalculator
{
    public function __construct(private DailyWage $wage) {}

    /**
     * @return array{monthly_wage: string, max_amount: string, max_months: int, max_installments: int, open: bool, first_month: string}
     */
    public function limits(Employee $employee): array
    {
        $monthly = $this->wage->monthly($this->wage->contractOf($employee));

        return [
            'monthly_wage' => (string) $monthly,
            'max_amount' => (string) $monthly->multipliedBy(RequestAdvance::MAX_MONTHS)->toScale(2),
            'max_months' => RequestAdvance::MAX_MONTHS,
            'max_installments' => RequestAdvance::MAX_INSTALLMENTS,
            'open' => Advance::query()->where('employee_id', $employee->id)->whereIn('status', [AdvanceStatus::Pending, AdvanceStatus::Approved, AdvanceStatus::Disbursed])->exists(),
            'first_month' => today()->addMonthNoOverflow()->startOfMonth()->format('Y-m'),
        ];
    }

    /**
     * @return array{installment: string, share_of_wage: string, schedule: list<array{due_month: string, amount: string}>}
     */
    public function preview(Employee $employee, string $amount, int $installments, string $firstMonth): array
    {
        $amount = BigDecimal::of($amount)->toScale(2, RoundingMode::HalfUp);
        $schedule = RequestAdvance::schedule($amount, max(1, $installments), Carbon::parse($firstMonth)->startOfMonth());
        $monthly = $this->wage->monthly($this->wage->contractOf($employee));

        return [
            'installment' => $schedule[0]['amount'],
            'share_of_wage' => $monthly->isPositive() ? (string) BigDecimal::of($schedule[0]['amount'])->multipliedBy(100)->dividedBy($monthly, 1, RoundingMode::HalfUp) : '0',
            'schedule' => $schedule,
        ];
    }
}
