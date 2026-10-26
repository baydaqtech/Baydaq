<?php

namespace App\Modules\Hr\Actions\Remote;

use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\RemoteRequest;
use App\Modules\Platform\Ports\Entitlements;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

/**
 * Remote and hybrid work. An employee works from home on a day when they are fully remote, when
 * the day is one of their hybrid remote weekdays, or when a request for that day was approved.
 * On such a day a punch outside every work location is accepted and marked remote.
 */
class RemoteWork
{
    public const MODES = ['onsite', 'remote', 'hybrid'];

    public function __construct(private Entitlements $entitlements) {}

    public function isRemoteDay(Employee $employee, string $date): bool
    {
        if (! $this->entitlements->hasFeature('hr.remote')) {
            return false;
        }

        return match ($employee->work_mode) {
            'remote' => true,
            'hybrid' => in_array(CarbonImmutable::parse($date)->dayOfWeek, array_map('intval', $employee->remote_days ?? []), true) || $this->approvedFor($employee, $date),
            default => $this->approvedFor($employee, $date),
        };
    }

    /**
     * @param  list<int>  $remoteDays
     */
    public function setPattern(Employee $employee, string $mode, array $remoteDays): Employee
    {
        $employee->forceFill([
            'work_mode' => $mode,
            'remote_days' => $mode === 'hybrid' ? array_values(array_unique(array_map('intval', $remoteDays))) : null,
        ])->save();

        return $employee;
    }

    public function request(Employee $employee, string $date, ?string $reason): RemoteRequest
    {
        if ($employee->work_mode === 'remote' || CarbonImmutable::parse($date)->lt(today())) {
            throw ValidationException::withMessages(['work_date' => __('hr.remote.errors.date')]);
        }

        $request = RemoteRequest::query()->where('employee_id', $employee->id)->whereDate('work_date', $date)->first()
            ?? (new RemoteRequest)->forceFill(['employee_id' => $employee->id, 'work_date' => $date]);

        if ($request->exists && $request->status !== 'cancelled' && $request->status !== 'rejected') {
            throw ValidationException::withMessages(['work_date' => __('hr.remote.errors.duplicate')]);
        }

        $request->forceFill(['reason' => $reason, 'status' => 'pending', 'decided_by' => null, 'decided_at' => null])->save();

        return $request;
    }

    public function decide(RemoteRequest $request, bool $approve, ?Authenticatable $actor = null): RemoteRequest
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['status' => __('hr.remote.errors.decided')]);
        }

        $request->forceFill(['status' => $approve ? 'approved' : 'rejected', 'decided_by' => $actor?->getAuthIdentifier(), 'decided_at' => now()])->save();

        return $request;
    }

    private function approvedFor(Employee $employee, string $date): bool
    {
        return RemoteRequest::query()->where('employee_id', $employee->id)->whereDate('work_date', $date)->where('status', 'approved')->exists();
    }
}
