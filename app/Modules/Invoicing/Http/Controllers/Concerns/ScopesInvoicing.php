<?php

namespace App\Modules\Invoicing\Http\Controllers\Concerns;

use App\Modules\Invoicing\Enums\InvoicingFeature;
use App\Modules\Platform\Data\CompanyRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Support\Collection;

/**
 * Invoices belong to companies; a company is in reach when the key covers the whole company.
 */
trait ScopesInvoicing
{
    use AuthorizesSuite;

    /**
     * @return Collection<int, CompanyRef>
     */
    protected function companiesFor(InvoicingFeature $feature, FeatureAction|string $action): Collection
    {
        $reach = $this->scope()->branchesFor($feature->key($action), $this->access());

        return collect(app(OrgDirectory::class)->companies())->filter(fn (CompanyRef $company): bool => $reach->allowsWholeCompany($company->id))->values();
    }

    protected function authorizeCompanyFor(InvoicingFeature $feature, FeatureAction|string $action, int $companyId): void
    {
        abort_unless($this->scope()->branchesFor($feature->key($action), $this->access())->allowsWholeCompany($companyId), 403);
    }
}
