<?php

namespace App\Modules\Hr\Ports;

/**
 * A check another module plugs into HR's offboarding: "this employee still holds X".
 */
interface OffboardingCheck
{
    /**
     * Reasons the employee cannot leave yet, in the current language (empty when clear).
     *
     * @return list<string>
     */
    public function blockers(int $employeeId): array;
}
