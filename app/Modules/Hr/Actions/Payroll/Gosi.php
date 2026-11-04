<?php

namespace App\Modules\Hr\Actions\Payroll;

use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Employee;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * GOSI contributions on the registrable wage (basic + allowances flagged counts_for_gosi, capped).
 * Saudis pay pension + SANED and the employer pays the same plus occupational hazards; for others
 * the employer pays occupational hazards only. The pension rate depends on the scheme: Saudis first
 * registered under the 2024 law pay a rate that steps up each July, everyone else the legacy rate.
 */
class Gosi
{
    public const LEGACY = 'legacy';

    public const NEW = 'new';

    /**
     * @return array{wage: BigDecimal, employee: BigDecimal, employer: BigDecimal, scheme: ?string, pension_rate: ?string}
     */
    public function handle(Employee $employee, ?Contract $contract, ?CarbonInterface $on = null): array
    {
        if ($contract === null) {
            return ['wage' => BigDecimal::zero(), 'employee' => BigDecimal::zero(), 'employer' => BigDecimal::zero(), 'scheme' => null, 'pension_rate' => null];
        }

        $config = config('baydaq.hr.gosi');
        $on = CarbonImmutable::parse($on ?? today());
        $wage = BigDecimal::of((string) $contract->basic_salary)
            ->plus($contract->allowances->filter(fn ($line) => $line->type?->counts_for_gosi)->reduce(fn (BigDecimal $sum, $line) => $sum->plus((string) $line->amount), BigDecimal::zero()));
        $wage = BigDecimal::min($wage, BigDecimal::of((string) $config['wage_cap']));
        $hazards = $wage->multipliedBy((string) $config['hazards']);

        if (! self::isSaudi($employee)) {
            return ['wage' => $wage->toScale(2, RoundingMode::HalfUp), 'employee' => BigDecimal::zero(), 'employer' => $hazards->toScale(2, RoundingMode::HalfUp), 'scheme' => null, 'pension_rate' => null];
        }

        $wage = BigDecimal::max($wage, BigDecimal::of((string) $config['wage_floor']));
        $scheme = self::scheme($employee);
        $pension = $scheme === self::NEW ? self::newPensionRate($on) : (string) $config['pension_legacy'];
        $share = $wage->multipliedBy(BigDecimal::of($pension)->plus((string) $config['saned']));

        return [
            'wage' => $wage->toScale(2, RoundingMode::HalfUp),
            'employee' => $share->toScale(2, RoundingMode::HalfUp),
            'employer' => $share->plus($wage->multipliedBy((string) $config['hazards']))->toScale(2, RoundingMode::HalfUp),
            'scheme' => $scheme,
            'pension_rate' => $pension,
        ];
    }

    public static function isSaudi(Employee $employee): bool
    {
        return strtoupper((string) $employee->nationality) === config('baydaq.hr.gosi.nationality');
    }

    /**
     * New-law scheme only when the employee's first GOSI registration is on or after the cut-off;
     * an unknown date keeps the legacy rate (the employee was most likely registered before).
     */
    public static function scheme(Employee $employee): string
    {
        $first = $employee->gosi_registered_on;

        return $first !== null && $first->toDateString() >= config('baydaq.hr.gosi.new_system_from') ? self::NEW : self::LEGACY;
    }

    public static function newPensionRate(CarbonInterface $on): string
    {
        $rate = (string) config('baydaq.hr.gosi.pension_legacy');

        foreach ((array) config('baydaq.hr.gosi.pension_new') as $from => $step) {
            if ($on->toDateString() >= $from) {
                $rate = (string) $step;
            }
        }

        return $rate;
    }
}
