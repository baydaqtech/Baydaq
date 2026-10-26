<?php

namespace App\Modules\Hr\Actions\Attendance;

use App\Modules\Hr\Models\AttendanceDevice;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Ports\Audit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

class BindDevice
{
    public function __construct(private Audit $audit) {}

    /**
     * Tie the employee to the phone they sign in from. The first sign-in binds; a different
     * phone is refused until a manager resets the binding. A device belongs to one employee.
     */
    public function handle(Employee $employee, string $deviceId, ?string $platform, ?string $model): AttendanceDevice
    {
        $existing = AttendanceDevice::query()->firstWhere('employee_id', $employee->id);

        if ($existing !== null) {
            if (! hash_equals($existing->device_id, $deviceId)) {
                throw ValidationException::withMessages(['device_id' => __('hr.attendance.errors.other_device')]);
            }

            return $existing;
        }

        if (AttendanceDevice::query()->where('device_id', $deviceId)->exists()) {
            throw ValidationException::withMessages(['device_id' => __('hr.attendance.errors.device_taken')]);
        }

        $device = new AttendanceDevice;
        $device->forceFill([
            'client_id' => $employee->client_id,
            'employee_id' => $employee->id,
            'device_id' => $deviceId,
            'platform' => $platform,
            'model' => $model,
            'bound_at' => now(),
        ])->save();

        return $device;
    }

    public function reset(Employee $employee, Authenticatable $actor): void
    {
        AttendanceDevice::query()->where('employee_id', $employee->id)->delete();
        $this->audit->record($employee, 'device_reset', null, $actor);
    }
}
