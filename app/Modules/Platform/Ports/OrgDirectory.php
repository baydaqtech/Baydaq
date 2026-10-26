<?php

namespace App\Modules\Platform\Ports;

use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Data\BrandRef;
use App\Modules\Platform\Data\CompanyRef;
use App\Modules\Platform\Data\WorkLocationRef;

/**
 * The current tenant's tree, read-only, for modules that place records under companies and
 * branches. Modules never query the platform tables themselves.
 */
interface OrgDirectory
{
    /** @return list<CompanyRef> */
    public function companies(): array;

    public function company(int $id): ?CompanyRef;

    /** @return list<BranchRef> all branches, or the branches of one company */
    public function branches(?int $companyId = null): array;

    public function branch(int $id): ?BranchRef;

    /** @return array<int, int> branch id → company id, for every branch of the tenant */
    public function branchCompanies(): array;

    /** @return list<WorkLocationRef> active geofences of the tenant, with the branches each serves */
    public function workLocations(): array;

    /** @return list<BrandRef> active brands, optionally of one company */
    public function brands(?int $companyId = null): array;
}
