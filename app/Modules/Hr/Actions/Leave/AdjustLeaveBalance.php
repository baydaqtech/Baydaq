<?php

namespace App\Modules\Hr\Actions\Leave;

use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveAdjustment;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Platform\Ports\Audit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

class AdjustLeaveBalance
{
    public function __construct(private Audit $audit) {}

    /** Add (or take away) days: an opening balance from the old system, a correction, a grant. */
    public function handle(Employee $employee, LeaveType $type, string $days, string $effectiveOn, string $reason, ?Authenticatable $actor = null): LeaveAdjustment
    {
        if (! $type->needs_balance || (float) $days === 0.0) {
            throw ValidationException::withMessages(['days' => __('hr.leave.errors.adjustment')]);
        }

        $adjustment = (new LeaveAdjustment)->forceFill([
            'client_id' => $employee->client_id, 'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'days' => $days, 'effective_on' => $effectiveOn, 'reason' => $reason, 'created_by' => $actor?->getAuthIdentifier(),
        ]);
        $adjustment->save();
        $this->audit->record($employee, 'leave_adjusted', ['type' => $type->id, 'days' => $days, 'reason' => $reason], $actor);

        return $adjustment;
    }
}
