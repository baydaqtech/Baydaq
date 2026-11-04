<?php

namespace App\Modules\Finance\Http\Controllers\Concerns;

use App\Modules\Finance\Enums\FinanceFeature;
use App\Modules\Platform\Access\BranchSet;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Data\CompanyRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use App\Modules\Platform\Ports\OrgDirectory;

/**
 * Finance records belong to a company and usually a branch, the same branches the rest of the
 * suite uses; these helpers narrow them to what the user reaches in the current view.
 */
trait ScopesFinance
{
    use AuthorizesSuite;

    protected function reach(FinanceFeature $feature, FeatureAction|string $action): BranchSet
    {
        return $this->scope()->branchesFor($feature->key($action), $this->access());
    }

    protected function authorizeBranch(FinanceFeature $feature, FeatureAction|string $action, int $branchId, int $companyId): void
    {
        abort_unless($this->reach($feature, $action)->allowsBranch($branchId, $companyId), 403);
    }

    /**
     * @return array{companies: list<array{value: int, label: string}>, branches: list<array{value: int, label: string, company_id: int}>}
     */
    protected function places(FinanceFeature $feature, FeatureAction|string $action): array
    {
        $reach = $this->reach($feature, $action);
        $directory = app(OrgDirectory::class);
        $branches = collect($directory->branches())->filter(fn (BranchRef $branch): bool => $reach->allowsBranch($branch->id, $branch->companyId));
        $companies = collect($directory->companies())->filter(fn (CompanyRef $company): bool => $branches->contains('companyId', $company->id))->keyBy('id');

        return [
            'companies' => $companies->map(fn (CompanyRef $company): array => ['value' => $company->id, 'label' => $company->name])->values()->all(),
            'branches' => $branches->map(fn (BranchRef $branch): array => ['value' => $branch->id, 'label' => ($companies->get($branch->companyId)?->name ?? '').' · '.$branch->name, 'company_id' => $branch->companyId])->values()->all(),
        ];
    }
}
