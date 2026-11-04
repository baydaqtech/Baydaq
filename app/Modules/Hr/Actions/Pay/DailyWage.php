<?php

namespace App\Modules\Hr\Actions\Pay;

use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Employee;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Wage figures everything else derives from: monthly wage (basic + allowances) and the daily
 * wage on a 30-day month, as the Labor Law counts it.
 */
class DailyWage
{
    public function contractOf(Employee $employee): ?Contract
    {
        return $employee->activeContract()->with('allowances')->first();
    }

    public function monthly(?Contract $contract): BigDecimal
    {
        if ($contract === null) {
            return BigDecimal::zero();
        }

        return BigDecimal::of((string) $contract->basic_salary)
            ->plus($contract->allowances->reduce(fn (BigDecimal $sum, $line) => $sum->plus((string) $line->amount), BigDecimal::zero()))
            ->toScale(2, RoundingMode::HalfUp);
    }

    public function daily(?Contract $contract): BigDecimal
    {
        return $this->monthly($contract)->dividedBy((string) config('baydaq.hr.labor.month_days', 30), 2, RoundingMode::HalfUp);
    }
}
