<?php

namespace App\Modules\Hr\Http\Controllers\Concerns;

use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Access\BranchSet;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use Illuminate\Database\Eloquent\Builder;

/**
 * Employee queries and record checks inside the current scope: what the permission reaches,
 * narrowed by the branch filter in the URL.
 */
trait ScopesEmployees
{
    use AuthorizesSuite;

    protected function reach(HrFeature $feature, FeatureAction|string $action): BranchSet
    {
        return $this->scope()->branchesFor($feature->key($action), $this->access());
    }

    /**
     * @return Builder<Employee>
     */
    protected function employees(FeatureAction|string $action = FeatureAction::View): Builder
    {
        return Employee::query()->visibleIn($this->reach(HrFeature::Employees, $action));
    }

    protected function authorizeEmployee(Employee $employee, HrFeature $feature, FeatureAction|string $action): void
    {
        abort_unless($this->reach($feature, $action)->allowsBranch($employee->branch_id, $employee->company_id), 403);
    }

    /** Whether the current user holds an extra ability over this employee (e.g. view_salaries). */
    protected function able(Employee $employee, HrFeature $feature, string $ability): bool
    {
        return $this->access()->allowsBranch($feature->key($ability), $employee->branch_id, $employee->company_id);
    }
}
