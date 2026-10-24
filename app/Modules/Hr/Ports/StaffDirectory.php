<?php

namespace App\Modules\Hr\Ports;

use App\Modules\Hr\Data\EmployeeSummary;

/**
 * Who works where, for other modules (POS cashiers, booking staff, custody holders).
 */
interface StaffDirectory
{
    public function employee(int $id): ?EmployeeSummary;

    public function employeeForUser(int $userId): ?EmployeeSummary;

    /** @return list<EmployeeSummary> active employees whose home or secondment covers the branch today */
    public function staffAt(int $branchId): array;
}
