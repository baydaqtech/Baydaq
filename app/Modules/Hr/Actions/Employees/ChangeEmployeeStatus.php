<?php

namespace App\Modules\Hr\Actions\Employees;

use App\Modules\Hr\Adapters\EmployeeStaffDirectory;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Events\EmployeeTerminated;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Ports\OffboardingClearance;
use App\Modules\Platform\Ports\Accounts;
use App\Modules\Platform\Ports\Audit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeEmployeeStatus
{
    public function __construct(private EnsureSeatAvailable $ensureSeatAvailable, private Audit $audit, private OffboardingClearance $clearance) {}

    /**
     * Move an employee between statuses. Entering a seated status takes a seat; terminating frees
     * it, closes the open placements and tells the other modules. Termination waits until every
     * module that lent the employee something (custody…) clears them.
     */
    public function handle(Employee $employee, EmployeeStatus $status, ?Authenticatable $actor = null, ?string $terminatedOn = null): Employee
    {
        if ($status === EmployeeStatus::Terminated && ($blockers = $this->clearance->blockers($employee->id)) !== []) {
            throw ValidationException::withMessages(['status' => $blockers]);
        }

        return DB::transaction(function () use ($employee, $status, $actor, $terminatedOn): Employee {
            $from = $employee->status;

            if ($status->usesSeat() && ! $from->usesSeat()) {
                $this->ensureSeatAvailable->handle($employee);
            }

            $employee->forceFill(['status' => $status->value]);

            if ($status === EmployeeStatus::Terminated) {
                $employee->terminated_on = $terminatedOn ?? today();
                $employee->placements()->whereNull('ends_on')->update(['ends_on' => $employee->terminated_on]);
            }

            $employee->save();
            $this->audit->record($employee, 'status_changed', ['from' => $from->value, 'to' => $status->value], $actor);

            if ($status === EmployeeStatus::Terminated) {
                EmployeeTerminated::dispatch(EmployeeStaffDirectory::summary($employee));
                // Sign the leaver out of the mobile app everywhere.
                if ($employee->user_id !== null) {
                    app(Accounts::class)->signOutEverywhere($employee->user_id);
                }
            }

            return $employee;
        });
    }
}
