<?php

namespace App\Modules\Hr\Ports;

/**
 * Query port: is this employee who they say they are, and are they at work right now? POS asks
 * before opening a cash drawer (HR PIN + attendance check-in).
 */
interface StaffPresence
{
    public function hasPin(int $employeeId): bool;

    public function verifyPin(int $employeeId, string $pin): bool;

    /** Their last punch today is a check-in at this branch (and wasn't rejected). */
    public function isClockedInAt(int $employeeId, int $branchId): bool;
}
