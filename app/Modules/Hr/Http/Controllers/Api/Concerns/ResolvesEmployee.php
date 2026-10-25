<?php

namespace App\Modules\Hr\Http\Controllers\Api\Concerns;

use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Models\Employee;
use Illuminate\Http\Request;

/**
 * The employee record behind the signed-in login; the app is for employees only.
 */
trait ResolvesEmployee
{
    protected function employee(Request $request): Employee
    {
        $employee = Employee::query()->with(['job', 'section'])->where('user_id', $request->user()->id)->firstOr(fn () => abort(403, __('hr.attendance.errors.not_employee')));

        // Someone who has left keeps no access to the app, even with an old token.
        abort_if($employee->status === EmployeeStatus::Terminated, 403, __('hr.attendance.errors.not_employee'));

        return $employee;
    }
}
