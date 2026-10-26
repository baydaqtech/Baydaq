<?php

namespace App\Modules\Hr\Ports;

use Carbon\CarbonInterface;

/**
 * Who can be booked at a branch on a day: other modules (Reservations) ask here instead of
 * reading placements, statuses or leave.
 */
interface StaffAvailability
{
    /**
     * Active employees working at the branch that day (home branch, or seconded there) and not
     * on approved leave. A seconded employee counts at the host branch only.
     *
     * @return list<int> employee ids
     */
    public function availableAt(int $branchId, CarbonInterface $date): array;
}
