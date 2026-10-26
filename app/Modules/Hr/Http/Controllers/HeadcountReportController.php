<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use Inertia\Response;

class HeadcountReportController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    /**
     * Headcount by branch and by section, over the branches the report permission reaches in
     * the current scope (so the branch filter in the URL is honoured, after intersection).
     */
    public function __invoke(OrgDirectory $directory): Response
    {
        $base = Employee::query()->visibleIn($this->reach(HrFeature::Reports, FeatureAction::View))->whereIn('status', EmployeeStatus::seated());
        $branchNames = collect($directory->branches())->mapWithKeys(fn (BranchRef $branch): array => [$branch->id => $branch->name]);

        $byBranch = (clone $base)->selectRaw('branch_id, count(*) as total')->groupBy('branch_id')->pluck('total', 'branch_id');
        $bySection = (clone $base)->with('section')->get()->groupBy(fn (Employee $employee) => $employee->section?->localized('name') ?? '—')->map->count();

        return $this->suitePage('Hr/Reports/Headcount', [
            'by_branch' => $byBranch->map(fn (int $total, int $branchId): array => ['branch' => $branchNames[$branchId] ?? (string) $branchId, 'total' => $total])->values()->all(),
            'by_section' => $bySection->map(fn (int $total, string $section): array => ['section' => $section, 'total' => $total])->values()->all(),
            'total' => (clone $base)->count(),
        ], ['hr']);
    }
}
