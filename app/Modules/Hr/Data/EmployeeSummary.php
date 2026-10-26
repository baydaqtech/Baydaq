<?php

namespace App\Modules\Hr\Data;

/**
 * An employee as other modules see them: identity and placement, never pay or ids.
 */
final readonly class EmployeeSummary
{
    public function __construct(
        public int $id,
        public int $companyId,
        public int $branchId,
        public string $employeeNumber,
        public string $name,
        public string $nameAr,
        public string $nameEn,
        public ?string $job,
        public ?string $section,
        public string $status,
        public ?int $userId,
        public ?string $phone = null,
        public ?string $gender = null,
    ) {}
}
