<?php

namespace App\Modules\Hr\Actions\Payroll;

use App\Modules\Hr\Actions\Pay\DailyWage;
use App\Modules\Hr\Models\Employee;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;

/**
 * End-of-service award per Saudi Labor Law Arts. 84–85, on the last wage (basic + allowances):
 * half a month per year for the first five years and a full month per year after that, pro rata
 * for parts of a year. On resignation the award is reduced: nothing under two years, one third
 * from two to five, two thirds from five to ten, in full from ten years. Termination by the
 * employer and contract expiry pay in full.
 */
class EndOfServiceBenefit
{
    public function __construct(private DailyWage $wage) {}

    /**
     * @param  'termination'|'resignation'|'contract_end'|'retirement'  $reason
     * @return array{years: float, monthly_wage: string, full_award: string, fraction: string, award: string}
     */
    public function handle(Employee $employee, string $reason, ?Carbon $lastDay = null): array
    {
        $lastDay ??= $employee->terminated_on ?? today();
        $monthly = $this->wage->monthly($this->wage->contractOf($employee));
        $years = ($employee->hired_on ?? $lastDay)->diffInDays($lastDay) / 365;

        $firstFive = min($years, 5);
        $beyond = max(0, $years - 5);
        $full = $monthly->multipliedBy(BigDecimal::of((string) $firstFive)->dividedBy(2, 6, RoundingMode::HalfUp))
            ->plus($monthly->multipliedBy((string) $beyond));

        $fraction = match (true) {
            $reason !== 'resignation' => '1',
            $years < 2 => '0',
            $years < 5 => '0.333333',
            $years < 10 => '0.666667',
            default => '1',
        };

        return [
            'years' => round($years, 2),
            'monthly_wage' => (string) $monthly,
            'full_award' => (string) $full->toScale(2, RoundingMode::HalfUp),
            'fraction' => $fraction,
            'award' => (string) $full->multipliedBy($fraction)->toScale(2, RoundingMode::HalfUp),
        ];
    }
}
