<?php

namespace App\Modules\Hr\Actions\Leave;

use App\Modules\Hr\Enums\LeaveStatus;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Platform\Ports\Audit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

class DecideLeave
{
    public function __construct(private Audit $audit) {}

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

        return $request;
    }
}
