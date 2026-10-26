<?php

namespace App\Modules\Hr\Actions\Shifts;

use App\Modules\Hr\Actions\Movements\ScheduleMovement;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\MovementType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeePlacement;
use App\Modules\Hr\Models\Shift;
use App\Modules\Platform\Ports\Audit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An employee picks up an open shift (H2). If the shift is at a branch that isn't theirs that
 * day, a one-day secondment is created, so their punch there counts as normal, not flagged.
 */
class ClaimOpenShift
{
    public function __construct(private ScheduleMovement $scheduleMovement, private Audit $audit) {}

    public function handle(Shift $shift, Employee $employee, ?Authenticatable $actor = null): Shift
    {
        return DB::transaction(function () use ($shift, $employee, $actor): Shift {
            $shift = Shift::query()->lockForUpdate()->findOrFail($shift->id);

            if (! $shift->is_open || $shift->employee_id !== null || $shift->shift_date->lt(today())) {
                throw ValidationException::withMessages(['shift' => __('hr.shifts.errors.not_open')]);
            }

            if (! in_array($employee->status, [EmployeeStatus::Active], true)) {
                throw ValidationException::withMessages(['shift' => __('hr.shifts.errors.not_active')]);
            }

            if ($shift->job_id !== null && $employee->job_id !== $shift->job_id) {
                throw ValidationException::withMessages(['shift' => __('hr.shifts.errors.other_job')]);
            }

            if (Shift::query()->where('employee_id', $employee->id)->whereDate('shift_date', $shift->shift_date)->exists()) {
                throw ValidationException::withMessages(['shift' => __('hr.shifts.errors.taken_that_day')]);
            }

            $covered = $employee->branch_id === $shift->branch_id || EmployeePlacement::query()->where('employee_id', $employee->id)->where('branch_id', $shift->branch_id)->coveringDate($shift->shift_date)->exists();
            $movementId = null;

            if (! $covered) {
                $movementId = $this->scheduleMovement->handle($employee, MovementType::Secondment, [
                    'to_branch_id' => $shift->branch_id,
                    'starts_on' => $shift->shift_date->toDateString(),
                    'ends_on' => $shift->shift_date->toDateString(),
                    'reason' => __('hr.shifts.secondment_reason', ['date' => $shift->shift_date->toDateString()]),
                ], $actor)->id;
            }

            $shift->forceFill(['employee_id' => $employee->id, 'is_open' => false, 'movement_id' => $movementId])->save();
            $this->audit->record($employee, 'shift_claimed', ['shift_id' => $shift->id, 'branch_id' => $shift->branch_id, 'date' => $shift->shift_date->toDateString()], $actor);

            return $shift;
        });
    }
}
