<?php

namespace App\Modules\Hr\Adapters;

use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Data\SearchContext;
use App\Modules\Platform\Data\SearchHit;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Ports\SuiteSearch;

class EmployeeSuiteSearch implements SuiteSearch
{
    public function search(string $term, SearchContext $context, int $limit = 5): array
    {
        return Employee::query()->visibleIn($context->reach(HrFeature::Employees->key(FeatureAction::View)))
            ->whereAny(['name_ar', 'name_en', 'employee_number', 'email', 'phone'], 'like', "%{$term}%")
            ->orderBy('name_en')->limit($limit)->get()
            ->map(fn (Employee $employee): SearchHit => new SearchHit('employee', $employee->localized('name'), $employee->employee_number, "/hr/employees/{$employee->id}"))
            ->all();
    }
}
