<?php

namespace App\Modules\Platform\Access;

use App\Modules\Platform\Models\Organization;
use Illuminate\Support\Collection;

/**
 * The tenant's tree trimmed to what one user can reach with any permission. It feeds the
 * scope switcher and decides where a user lands.
 */
class ReachableTree
{
    /**
     * @param  string|null  $key  limit to what this permission reaches; null means any permission
     * @return Collection<int, Organization> organizations with reachable companies and branches loaded
     */
    public function for(AccessMap $access, ?string $key = null): Collection
    {
        $reach = $key === null ? $this->anyReach($access) : $access->branches($key);

        if ($reach->isEmpty()) {
            return collect();
        }

        return Organization::query()
            ->with(['companies' => fn ($query) => $query->orderBy('name_en')->with(['branches' => fn ($query) => $query->orderByDesc('is_head_office')->orderBy('name_en')])])
            ->orderBy('name_en')
            ->get()
            ->each(function (Organization $organization) use ($reach): void {
                $organization->setRelation('companies', $organization->companies->each(function ($company) use ($reach): void {
                    $company->setRelation('branches', $company->branches->filter(
                        fn ($branch) => $reach->allowsBranch($branch->id, $company->id),
                    )->values());
                })->filter(fn ($company) => $company->branches->isNotEmpty())->values());
            })
            ->filter(fn (Organization $organization) => $organization->companies->isNotEmpty())
            ->values();
    }

    /** Everything the user reaches with at least one permission. */
    public function anyReach(AccessMap $access): BranchSet
    {
        if ($access->isOwner()) {
            return BranchSet::all();
        }

        return array_reduce(
            $access->keys(),
            fn (BranchSet $carry, string $key): BranchSet => $carry->merge($access->branches($key)),
            BranchSet::none(),
        );
    }
}
