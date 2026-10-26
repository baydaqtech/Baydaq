<?php

namespace App\Modules\Hr\Actions\Compliance;

use App\Modules\Hr\Actions\Pay\DailyWage;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Models\Employee;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Saudization per company as Nitaqat counts it (H4). From 2026 a Saudi counts in full only with
 * a Qiwa-authenticated contract paying at least SAR 4,000; authenticated below that counts half;
 * an unauthenticated contract doesn't count. Everyone else on the payroll counts as one expat.
 */
class NitaqatMeter
{
    public const FULL_WAGE = '4000';

    public function __construct(private DailyWage $wage) {}

    /**
     * @return array{saudis: float, saudis_headcount: int, expats: int, total: float, percent: float, people: list<array{id: int, name: string, weight: float, reason: string}>}
     */
    public function forCompany(int $companyId): array
    {
        $employees = Employee::query()->where('company_id', $companyId)->whereIn('status', EmployeeStatus::seated())->get();
        $people = [];
        $saudis = 0.0;
        $expats = 0;

        foreach ($employees as $employee) {
            if ($employee->nationality !== 'SA') {
                $expats++;

                continue;
            }

            $contract = $this->wage->contractOf($employee);
            $monthly = $this->wage->monthly($contract);
            [$weight, $reason] = match (true) {
                $contract === null || ! $contract->qiwa_authenticated => [0.0, 'not_authenticated'],
                $monthly->isLessThan(self::FULL_WAGE) => [0.5, 'below_wage'],
                default => [1.0, 'counts'],
            };

            $saudis += $weight;
            $people[] = ['id' => $employee->id, 'name' => $employee->localized('name'), 'weight' => $weight, 'reason' => $reason];
        }

        $total = $saudis + $expats;

        return [
            'saudis' => $saudis,
            'saudis_headcount' => count($people),
            'expats' => $expats,
            'total' => $total,
            'percent' => $total > 0 ? (float) (string) BigDecimal::of((string) $saudis)->multipliedBy(100)->dividedBy((string) $total, 1, RoundingMode::HalfUp) : 0.0,
            'people' => $people,
        ];
    }

    /** Full-weight Saudis to add to reach the target (0 when already there). */
    public static function saudisNeeded(float $saudis, float $total, float $target): int
    {
        if ($target <= 0 || $target >= 100 || $total <= 0 || $saudis / $total * 100 >= $target) {
            return 0;
        }

        // (saudis + n) / (total + n) >= target / 100  →  n >= (target·total − 100·saudis) / (100 − target)
        return (int) ceil(($target * $total - 100 * $saudis) / (100 - $target) - 1e-9);
    }
}
