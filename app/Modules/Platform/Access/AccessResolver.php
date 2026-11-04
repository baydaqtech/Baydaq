<?php

namespace App\Modules\Platform\Access;

use App\Models\User;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Access\AccessAssignment;
use App\Modules\Platform\Models\Access\AccessRole;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Container\Attributes\Scoped;

/**
 * Turns a user's assignments in the current tenant into an AccessMap. Memoized per user
 * for the request, so controllers, policies, resources and the shared props all read the
 * same answer from one query.
 */
#[Scoped]
class AccessResolver
{
    /** @var array<int, AccessMap> */
    private array $maps = [];

    public function __construct(
        private readonly FeatureRegistry $registry,
        private readonly CurrentTenant $tenant,
    ) {}

    public function for(User $user): AccessMap
    {
        return $this->maps[$user->id] ??= $this->build($user);
    }

    public function forget(?User $user = null): void
    {
        if ($user === null) {
            $this->maps = [];
        } else {
            unset($this->maps[$user->id]);
        }
    }

    private function build(User $user): AccessMap
    {
        if (! $this->tenant->isSet() || $user->client_id !== $this->tenant->id()) {
            return AccessMap::empty();
        }

        $assignments = AccessAssignment::query()
            ->where('user_id', $user->id)
            ->with('role.permissions')
            ->get();

        if ($assignments->isEmpty()) {
            return AccessMap::empty();
        }

        $organizationIds = $assignments->where('level', ScopeLevel::Organization)->pluck('organization_id')->unique()->all();
        $organizationCompanies = $organizationIds === [] ? collect() : Company::query()
            ->whereIn('organization_id', $organizationIds)
            ->get(['id', 'organization_id'])
            ->groupBy('organization_id')
            ->map(fn ($companies) => $companies->pluck('id')->all());

        $grants = [];

        foreach ($assignments as $assignment) {
            $reach = match ($assignment->level) {
                ScopeLevel::Client => BranchSet::all(),
                ScopeLevel::Organization => BranchSet::of($organizationCompanies->get($assignment->organization_id, [])),
                ScopeLevel::Company => BranchSet::of([$assignment->company_id]),
                ScopeLevel::Branch => BranchSet::of([], [$assignment->branch_id]),
            };

            $keys = $assignment->role->permissions->pluck('permission')->all();

            if (in_array(AccessRole::WILDCARD, $keys, true)) {
                if ($assignment->level === ScopeLevel::Client) {
                    return new AccessMap([], true, $this->registry->keys());
                }

                $keys = $this->registry->keys();
            }

            foreach ($keys as $key) {
                $grants[$key] = isset($grants[$key]) ? $grants[$key]->merge($reach) : $reach;
            }
        }

        return new AccessMap($grants, false, $this->registry->keys());
    }
}
