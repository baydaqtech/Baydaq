<?php

namespace App\Modules\Platform\Http\Controllers\Concerns;

use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Data\CompanyRef;
use App\Modules\Platform\Enums\Contracts\Feature;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Support\Collection;

/**
 * For module controllers: the branches and companies the user reaches with a permission inside
 * the current scope (and its branch filter), as Platform Data objects.
 */
trait ReachesBranches
{
    use AuthorizesSuite;

    /** @return Collection<int, BranchRef> */
    protected function branchesFor(Feature $feature, FeatureAction|string $action): Collection
    {
        $reach = $this->scope()->branchesFor($feature->key($action), $this->access());

        return collect(app(OrgDirectory::class)->branches())->filter(fn (BranchRef $branch): bool => $reach->allowsBranch($branch->id, $branch->companyId))->values();
    }

    /** @return Collection<int, CompanyRef> */
    protected function companiesFor(Feature $feature, FeatureAction|string $action): Collection
    {
        $companyIds = $this->branchesFor($feature, $action)->pluck('companyId')->unique();

        return collect(app(OrgDirectory::class)->companies())->filter(fn (CompanyRef $company): bool => $companyIds->contains($company->id))->values();
    }

    protected function authorizeBranchFor(Feature $feature, FeatureAction|string $action, int $branchId): void
    {
        abort_unless($this->branchesFor($feature, $action)->contains(fn (BranchRef $branch): bool => $branch->id === $branchId), 403);
    }

    protected function authorizeCompanyFor(Feature $feature, FeatureAction|string $action, int $companyId): void
    {
        abort_unless($this->companiesFor($feature, $action)->contains(fn (CompanyRef $company): bool => $company->id === $companyId), 403);
    }
}
