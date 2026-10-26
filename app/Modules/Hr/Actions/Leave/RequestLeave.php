<?php

namespace App\Modules\Hr\Actions\Leave;

use App\Modules\Hr\Enums\LeaveStatus;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Platform\Ports\Audit;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class RequestLeave
{
    public function __construct(private Audit $audit, private LeaveBalance $balance) {}

    /**
     * @param  array{starts_on: string, ends_on: string, reason?: ?string}  $data
     */
    public function handle(Employee $employee, LeaveType $type, array $data, ?Authenticatable $actor = null): LeaveRequest
    {
        $starts = Carbon::parse($data['starts_on'])->startOfDay();
        $ends = Carbon::parse($data['ends_on'])->startOfDay();
        $days = (int) $starts->diffInDays($ends) + 1;

        if ($ends->lt($starts)) {
            throw ValidationException::withMessages(['ends_on' => __('hr.leave.errors.period')]);
        }

        $overlaps = LeaveRequest::query()->where('employee_id', $employee->id)
            ->whereIn('status', [LeaveStatus::Pending, LeaveStatus::Approved])
            ->whereDate('starts_on', '<=', $ends)->whereDate('ends_on', '>=', $starts)->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['starts_on' => __('hr.leave.errors.overlap')]);
        }

        // Pending requests already spoken for count against the balance too.
        if ($type->needs_balance && ! $type->allow_negative) {
            $available = BigDecimal::of($this->balance->handle($employee, $type, $starts)['available_after_pending']);

            if ($available->isLessThan($days)) {
                throw ValidationException::withMessages(['days' => __('hr.leave.errors.balance', ['available' => (string) $available])]);
            }
        }

        $request = new LeaveRequest(['starts_on' => $starts->toDateString(), 'ends_on' => $ends->toDateString(), 'reason' => $data['reason'] ?? null]);
        $request->forceFill([
            'client_id' => $employee->client_id,
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'days' => $days,
            'status' => LeaveStatus::Pending->value,
            'requested_by' => $actor?->getAuthIdentifier(),
        ])->save();

        $this->audit->record($request, 'requested', ['days' => $days], $actor);

        return $request;
    }
}
