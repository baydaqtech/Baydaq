<?php

namespace App\Modules\Hr\Models\Concerns;

use App\Modules\Hr\Models\Employee;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Rows addressed to an audience: no company = the whole account, a company, or one branch of it.
 */
trait HasAudience
{
    /**
     * What this employee is part of the audience for.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function forEmployee(Builder $query, Employee $employee): void
    {
        $query->where(fn (Builder $query) => $query->whereNull($this->qualifyColumn('company_id'))->orWhere($this->qualifyColumn('company_id'), $employee->company_id))
            ->where(fn (Builder $query) => $query->whereNull($this->qualifyColumn('branch_id'))->orWhere($this->qualifyColumn('branch_id'), $employee->branch_id));
    }

    /**
     * Rows a manager sees: account-wide ones, and those for the companies or branches their staff work in.
     *
     * @param  Builder<static>  $query
     * @param  list<int>  $companyIds
     * @param  list<int>  $branchIds
     */
    #[Scope]
    protected function forPlaces(Builder $query, array $companyIds, array $branchIds): void
    {
        $query->where(fn (Builder $query) => $query->whereNull($this->qualifyColumn('company_id'))
            ->orWhere(fn (Builder $query) => $query->whereIn($this->qualifyColumn('company_id'), $companyIds)->whereNull($this->qualifyColumn('branch_id')))
            ->orWhereIn($this->qualifyColumn('branch_id'), $branchIds));
    }

    /**
     * Working employees in this row's audience.
     *
     * @return Builder<Employee>
     */
    public function audienceEmployees(): Builder
    {
        return Employee::query()
            ->when($this->company_id !== null, fn (Builder $query) => $query->where('company_id', $this->company_id))
            ->when($this->branch_id !== null, fn (Builder $query) => $query->where('branch_id', $this->branch_id));
    }
}
