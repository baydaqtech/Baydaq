<?php

namespace App\Modules\Platform\Adapters;

use App\Models\User;
use App\Modules\Platform\Access\AccessQuery;
use App\Modules\Platform\Access\AccessResolver;
use App\Modules\Platform\Access\BranchSet;
use App\Modules\Platform\Access\SuiteScope;
use App\Modules\Platform\Actions\Access\AssignAccess;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Access\AccessRole;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Ports\Access;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;

class ResolvedAccess implements Access
{
    public function __construct(private AccessResolver $resolver, private AccessQuery $query, private AssignAccess $assignAccess) {}

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

    public function roles(): array
    {
        return AccessRole::query()->where(fn ($query) => $query->whereNull('key')->orWhere('key', '!=', AccessRole::OWNER_KEY))
            ->orderBy('name_en')->get()
            ->map(fn (AccessRole $role): array => ['id' => $role->id, 'name' => $role->localized('name')])->all();
    }

    public function grantAtBranch(int $userId, int $roleId, int $branchId, ?Authenticatable $actor): void
    {
        $role = AccessRole::query()->with('permissions')->findOrFail($roleId);
        $user = User::query()->where('client_id', $role->client_id)->findOrFail($userId);

        $actor = $actor instanceof User ? $actor : null;
        $this->assignAccess->handle($user, $role, ScopeLevel::Branch, Branch::query()->findOrFail($branchId), $actor, guard: $actor !== null);
    }
}
