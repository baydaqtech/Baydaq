<?php

namespace App\Modules\Platform\Adapters;

use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Data\BrandRef;
use App\Modules\Platform\Data\CompanyRef;
use App\Modules\Platform\Data\WorkLocationRef;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Brand;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\WorkLocation;
use App\Modules\Platform\Ports\OrgDirectory;

class TenantOrgDirectory implements OrgDirectory
{
    public function companies(): array
    {
        return Company::query()->orderBy('name_en')->get()->map(fn (Company $company): CompanyRef => $this->companyRef($company))->all();
    }

    public function company(int $id): ?CompanyRef
    {
        $company = Company::query()->find($id);

        return $company ? $this->companyRef($company) : null;
    }

    public function branches(?int $companyId = null): array
    {
        return Branch::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->orderByDesc('is_head_office')->orderBy('name_en')
            ->get()
            ->map(fn (Branch $branch): BranchRef => $this->branchRef($branch))
            ->all();
    }

    public function branch(int $id): ?BranchRef
    {
        $branch = Branch::query()->find($id);

        return $branch ? $this->branchRef($branch) : null;
    }

    public function branchCompanies(): array
    {
        return Branch::query()->pluck('company_id', 'id')->map(fn ($id): int => (int) $id)->all();
    }

    public function brands(?int $companyId = null): array
    {
        return Brand::query()->where('is_active', true)->when($companyId, fn ($query) => $query->where('company_id', $companyId))->with('branches:id')->orderBy('name_en')->get()
            ->map(fn (Brand $brand): BrandRef => new BrandRef($brand->id, $brand->company_id, $brand->slug, $brand->localized('name'), $brand->name_ar, $brand->name_en, $brand->color, $brand->branches->pluck('id')->map(fn ($id): int => (int) $id)->all()))
            ->all();
    }

    public function workLocations(): array
    {
        return WorkLocation::query()->where('is_active', true)->with('branches:id')->get()
            ->map(fn (WorkLocation $location): WorkLocationRef => new WorkLocationRef(
                $location->id, $location->localized('name'), $location->latitude, $location->longitude, $location->radius_m,
                $location->branches->pluck('id')->map(fn ($id): int => (int) $id)->all(), $location->wifi_bssids ?? [],
            ))->all();
    }

    private function companyRef(Company $company): CompanyRef
    {
        return new CompanyRef($company->id, $company->organization_id, $company->slug, $company->localized('name'), $company->name_ar, $company->name_en, $company->timezone, $company->status === 'active',
            $company->vat_number, $company->cr_number, ['street' => $company->street, 'building_number' => $company->building_number, 'district' => $company->district, 'city' => $company->city, 'postal_code' => $company->postal_code]);
    }

    private function branchRef(Branch $branch): BranchRef
    {
        return new BranchRef($branch->id, $branch->company_id, $branch->code, $branch->localized('name'), $branch->name_ar, $branch->name_en, $branch->is_head_office, $branch->status === 'active');
    }
}
