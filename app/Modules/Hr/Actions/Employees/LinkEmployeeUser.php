<?php

namespace App\Modules\Hr\Actions\Employees;

use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Ports\Accounts;
use App\Modules\Platform\Ports\Audit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

class LinkEmployeeUser
{
    public function __construct(private Accounts $accounts, private Audit $audit) {}

    /**
     * Give an employee a login: reuse the tenant's login with that email or invite a new one.
     * Roles are granted separately; a bare login means self-service only.
     */
    public function handle(Employee $employee, string $email, ?Authenticatable $actor = null): Employee
    {
        if ($employee->user_id !== null) {
            throw ValidationException::withMessages(['email' => __('hr.employees.errors.already_linked')]);
        }

        $userId = $this->accounts->findByEmail($email);

        if ($userId !== null && Employee::query()->where('user_id', $userId)->exists()) {
            throw ValidationException::withMessages(['email' => __('hr.employees.errors.login_taken')]);
        }

        $userId ??= $this->accounts->invite($employee->localized('name'), $email, app()->getLocale());

        $employee->forceFill(['user_id' => $userId])->save();
        $this->audit->record($employee, 'login_linked', ['user_id' => $userId], $actor);

        return $employee;
    }
}
