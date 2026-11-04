<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use Inertia\Response;

class OrgChartController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    /**
     * Everyone in scope as a flat list with manager ids; the page draws the tree. Managers
     * outside the scope show as placeholders so the tree still hangs together.
     */
    public function __invoke(): Response
    {
        $employees = $this->employees()->with(['job', 'section'])->whereIn('status', EmployeeStatus::seated())->orderBy('name_en')->get();
        $ids = $employees->pluck('id');

        $outside = Employee::query()->whereIn('id', $employees->pluck('manager_id')->filter()->diff($ids))->get(['id', 'name_ar', 'name_en']);

        return $this->suitePage('Hr/OrgChart', [
            'nodes' => $employees->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->localized('name'),
                'job' => $employee->job?->localized('name'),
                'section' => $employee->section?->localized('name'),
                'manager_id' => $employee->manager_id,
                'outside' => false,
            ])->concat($outside->map(fn (Employee $employee): array => [
                'id' => $employee->id, 'name' => $employee->localized('name'), 'job' => null, 'section' => null, 'manager_id' => null, 'outside' => true,
            ]))->values()->all(),
        ], ['hr']);
    }
}
