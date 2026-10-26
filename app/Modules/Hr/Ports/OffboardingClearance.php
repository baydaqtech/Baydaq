<?php

namespace App\Modules\Hr\Ports;

/**
 * HR's offboarding extension point. Modules that lend things to employees (Assets: custody)
 * register a check in their service provider; HR asks every check before terminating someone,
 * so HR never depends on those modules.
 */
final class OffboardingClearance
{
    /** @var list<OffboardingCheck> */
    private array $checks = [];

    public function register(OffboardingCheck $check): void
    {
        $this->checks[] = $check;
    }

    /**
     * @return list<string>
     */
    public function blockers(int $employeeId): array
    {
        return array_values(array_merge(...array_map(fn (OffboardingCheck $check): array => $check->blockers($employeeId), $this->checks ?: [new class implements OffboardingCheck
        {
            public function blockers(int $employeeId): array
            {
                return [];
            }
        }])));
    }
}
