<?php

namespace App\Modules\Hr\Adapters;

use App\Modules\Hr\Enums\PunchResolution;
use App\Modules\Hr\Enums\PunchType;
use App\Modules\Hr\Models\AttendancePunch;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Ports\StaffPresence;
use Illuminate\Support\Facades\Hash;

class EmployeeStaffPresence implements StaffPresence
{
    public function hasPin(int $employeeId): bool
    {
        return Employee::query()->whereKey($employeeId)->whereNotNull('pin_hash')->exists();
    }

    public function verifyPin(int $employeeId, string $pin): bool
    {
        $hash = Employee::query()->whereKey($employeeId)->value('pin_hash');

        return $hash !== null && Hash::check($pin, $hash);
    }

    public function isClockedInAt(int $employeeId, int $branchId): bool
    {
        $last = AttendancePunch::query()->where('employee_id', $employeeId)->whereDate('work_date', today())
            ->where(fn ($query) => $query->whereNull('resolution')->orWhere('resolution', '!=', PunchResolution::Rejected->value))
            ->latest('punched_at')->first();

        return $last !== null && $last->type === PunchType::In && (int) $last->branch_id === $branchId;
    }
}
