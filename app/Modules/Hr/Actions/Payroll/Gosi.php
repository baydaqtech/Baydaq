<?php

namespace App\Modules\Hr\Actions\Payroll;

use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Employee;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * GOSI contributions on the registrable wage (basic + allowances flagged counts_for_gosi, capped).
 * Saudis pay their share and the employer pays pension + SANED + hazards; for others the employer
 * pays occupational hazards only. Rates live in config and are reviewed each July.
 */
class Gosi
{
    /**
     * @return array{wage: BigDecimal, employee: BigDecimal, employer: BigDecimal}
     */
    public function handle(Employee $employee, ?Contract $contract): array
    {
        if ($contract === null) {
            return ['wage' => BigDecimal::zero(), 'employee' => BigDecimal::zero(), 'employer' => BigDecimal::zero()];
        }

        $config = config('baydaq.hr.gosi');
        $wage = BigDecimal::of((string) $contract->basic_salary)
            ->plus($contract->allowances->filter(fn ($line) => $line->type?->counts_for_gosi)->reduce(fn (BigDecimal $sum, $line) => $sum->plus((string) $line->amount), BigDecimal::zero()));
        $wage = BigDecimal::min($wage, BigDecimal::of((string) $config['wage_cap']));
        $saudi = strtoupper((string) $employee->nationality) === $config['nationality'];

        return [
            'wage' => $wage->toScale(2, RoundingMode::HalfUp),
            'employee' => $saudi ? $wage->multipliedBy((string) $config['saudi_employee'])->toScale(2, RoundingMode::HalfUp) : BigDecimal::zero(),
            'employer' => $wage->multipliedBy((string) ($saudi ? $config['saudi_employer'] : $config['non_saudi_employer']))->toScale(2, RoundingMode::HalfUp),
        ];
    }
}
