<?php

namespace App\Modules\Platform\Adapters;

use App\Models\User;
use App\Modules\Platform\Access\AccessQuery;
use App\Modules\Platform\Access\AccessResolver;
use App\Modules\Platform\Access\BranchSet;
use App\Modules\Platform\Access\SuiteScope;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Ports\Access;
use Illuminate\Database\Eloquent\Collection;

class ResolvedAccess implements Access
{
    public function __construct(private AccessResolver $resolver, private AccessQuery $query) {}

    public function allows(string $key, ?User $user = null): bool
    {
        return $this->resolver->for($user ?? auth()->user())->allows($key);
    }

    public function branchesFor(string $key, ?User $user = null): BranchSet
    {
        $map = $this->resolver->for($user ?? auth()->user());

        return app()->bound(SuiteScope::class) ? app(SuiteScope::class)->branchesFor($key, $map) : $map->branches($key);
    }

    public function allowsBranch(string $key, int $branchId, int $companyId, ?User $user = null): bool
    {
        return $this->branchesFor($key, $user)->allowsBranch($branchId, $companyId);
    }

    public function usersWith(string $key, int $branchId): Collection
    {
        return $this->query->usersWith($key, Branch::query()->findOrFail($branchId));
    }
}
