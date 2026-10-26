<?php

namespace App\Modules\Platform\Access;

use App\Models\User;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Access\AccessRole;
use App\Modules\Platform\Models\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The reverse question, answered in SQL: which users hold a key over a given branch?
 * Used to pick who gets an alert, without resolving every user's map.
 */
class AccessQuery
{
    /**
     * @return Collection<int, User>
     */
    public function usersWith(string $key, Branch $branch): Collection
    {
        $branch->loadMissing('company');

        return User::query()
            ->whereHas('accessAssignments', function (Builder $query) use ($key, $branch): void {
                $query->where('client_id', $branch->client_id)
                    ->whereHas('role.permissions', fn (Builder $query) => $query->whereIn('permission', [$key, AccessRole::WILDCARD]))
                    ->where(function (Builder $query) use ($branch): void {
                        $query->where('level', ScopeLevel::Client)
                            ->orWhere(fn (Builder $query) => $query->where('level', ScopeLevel::Organization)->where('organization_id', $branch->company->organization_id))
                            ->orWhere(fn (Builder $query) => $query->where('level', ScopeLevel::Company)->where('company_id', $branch->company_id))
                            ->orWhere(fn (Builder $query) => $query->where('level', ScopeLevel::Branch)->where('branch_id', $branch->id));
                    });
            })
            ->get();
    }
}
