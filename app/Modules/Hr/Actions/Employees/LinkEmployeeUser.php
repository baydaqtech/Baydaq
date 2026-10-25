<?php

namespace App\Modules\Hr\Actions\Employees;

use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Ports\Access;
use App\Modules\Platform\Ports\Accounts;
use App\Modules\Platform\Ports\Audit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LinkEmployeeUser
{
    public function __construct(private Accounts $accounts, private Audit $audit, private Access $access) {}

    /**
     * Give an employee a login: reuse the tenant's login with that email or invite a new one.
     * A bare login means self-service only. With $grantJobRole, the role their job suggests is
     * also given at their home branch (the actor must hold that role's keys there).
     */
    public function handle(Employee $employee, string $email, ?Authenticatable $actor = null, bool $grantJobRole = false): Employee
    {
        if ($employee->user_id !== null) {
            throw ValidationException::withMessages(['email' => __('hr.employees.errors.already_linked')]);
        }

        $userId = $this->accounts->findByEmail($email);

        if ($userId !== null && Employee::query()->where('user_id', $userId)->exists()) {
            throw ValidationException::withMessages(['email' => __('hr.employees.errors.login_taken')]);
        }

        return DB::transaction(function () use ($employee, $email, $actor, $grantJobRole, $userId): Employee {
            $userId ??= $this->accounts->invite($employee->localized('name'), $email, app()->getLocale());

            $roleId = $grantJobRole ? $employee->job?->default_role_id : null;

            if ($roleId !== null) {
                $this->access->grantAtBranch($userId, $roleId, $employee->branch_id, $actor);
            }

            $employee->forceFill(['user_id' => $userId])->save();
            $this->audit->record($employee, 'login_linked', ['user_id' => $userId, 'role_id' => $roleId], $actor);

            return $employee;
        });
    }
}
