<?php

namespace App\Modules\Hr\Actions\Leave;

use App\Modules\Hr\Enums\LeaveStatus;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Support\EmployeeMessenger;
use App\Modules\Platform\Ports\Audit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

class DecideLeave
{
    public function __construct(private Audit $audit, private EmployeeMessenger $messenger) {}

    public function handle(LeaveRequest $request, LeaveStatus $decision, ?string $note, Authenticatable $actor): LeaveRequest
    {
        $allowed = match ($request->status) {
            LeaveStatus::Pending => [LeaveStatus::Approved, LeaveStatus::Rejected, LeaveStatus::Cancelled],
            LeaveStatus::Approved => [LeaveStatus::Cancelled],
            default => [],
        };

        if (! in_array($decision, $allowed, true)) {
            throw ValidationException::withMessages(['status' => __('hr.leave.errors.decision')]);
        }

        $request->forceFill(['status' => $decision->value, 'decision_note' => $note, 'decided_by' => $actor->getAuthIdentifier(), 'decided_at' => now()])->save();
        $this->audit->record($request, $decision->value, $note ? ['note' => $note] : null, $actor);

        if (in_array($decision, [LeaveStatus::Approved, LeaveStatus::Rejected], true)) {
            $this->messenger->send($request->employee, 'hr_leave_decided', [
                'type' => $request->type->localized('name'), 'from' => $request->starts_on->toDateString(), 'to' => $request->ends_on->toDateString(), 'status' => __("hr.leave_status.{$decision->value}"),
            ], "hr.leave:{$request->id}");
        }

        return $request;
    }
}
