<?php

namespace App\Modules\Hr\Adapters;

use App\Modules\Hr\Data\EmployeeSummary;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeePlacement;
use App\Modules\Hr\Ports\StaffDirectory;

class EmployeeStaffDirectory implements StaffDirectory
{
    public function employee(int $id): ?EmployeeSummary
    {
        $employee = Employee::query()->with(['job', 'section'])->find($id);

        return $employee ? self::summary($employee) : null;
    }

    public function employeeForUser(int $userId): ?EmployeeSummary
    {
        $employee = Employee::query()->with(['job', 'section'])->firstWhere('user_id', $userId);

        return $employee ? self::summary($employee) : null;
    }

    public function staffAt(int $branchId): array
    {
        $ids = EmployeePlacement::query()->where('branch_id', $branchId)->coveringDate(today())->pluck('employee_id');

        return Employee::query()->with(['job', 'section'])
            ->whereIn('id', $ids)->where('status', EmployeeStatus::Active)
            ->orderBy('name_en')->get()
            ->map(fn (Employee $employee): EmployeeSummary => self::summary($employee))->all();
    }

    public static function summary(Employee $employee): EmployeeSummary
    {
        return new EmployeeSummary(
            $employee->id, $employee->company_id, $employee->branch_id, $employee->employee_number,
            $employee->localized('name'), $employee->name_ar, $employee->name_en,
            $employee->job?->localized('name'), $employee->section?->localized('name'),
            $employee->status->value, $employee->user_id,
        );
    }
}
