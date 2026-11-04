<?php

namespace App\Modules\Hr\Support;

use App\Modules\Hr\Enums\LeaveStatus;
use App\Modules\Hr\Models\LeaveRequest;
use Illuminate\Validation\ValidationException;

/**
 * One answer to "is this person free to work that day?" for everything that plans an employee's
 * day: remote days, shifts and open-shift claims (bookable staff already skip approved leave).
 * Leave wins: a day off, asked for or approved, can't also be a remote day or a shift. Leave
 * itself may still be asked for over planned work; the manager then moves the shift.
 */
class ScheduleConflicts
{
    /** Leave, asked for or approved, that covers the date. */
    public function leaveOn(int $employeeId, string $date): ?LeaveRequest
    {
        return LeaveRequest::query()->where('employee_id', $employeeId)->whereIn('status', [LeaveStatus::Pending, LeaveStatus::Approved])
            ->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->first();
    }

    /** Refuse planning work on a leave day, with the leave's dates in the message. */
    public function ensureFreeToWork(int $employeeId, string $date, string $field): void
    {
        $leave = $this->leaveOn($employeeId, $date);

        if ($leave !== null) {
            throw ValidationException::withMessages([$field => __($leave->status === LeaveStatus::Approved ? 'hr.schedule.on_leave' : 'hr.schedule.asked_leave', [
                'from' => $leave->starts_on->toDateString(), 'to' => $leave->ends_on->toDateString(),
            ])]);
        }
    }
}
