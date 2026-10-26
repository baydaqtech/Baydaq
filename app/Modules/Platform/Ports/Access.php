<?php

namespace App\Modules\Platform\Ports;

use App\Models\User;
use App\Modules\Platform\Access\BranchSet;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Who may do what, for the signed-in user in the current scope. Modules filter their
 * queries with branchesFor() and check single records with allowsBranch().
 */
interface Access
{
    /** Holds the key anywhere in the tenant. */
    public function allows(string $key, ?User $user = null): bool;

    /** The branches the key reaches inside the current scope (narrowed by the branch filter). */
    public function branchesFor(string $key, ?User $user = null): BranchSet;

    public function allowsBranch(string $key, int $branchId, int $companyId, ?User $user = null): bool;

    /**
     * Users holding the key over a branch (for alerts).
     *
     * @return Collection<int, User>
     */
    public function usersWith(string $key, int $branchId): Collection;

    /**
     * The tenant's roles, for pickers in other modules (the owner role is left out).
     *
     * @return list<array{id: int, name: string}>
     */
    public function roles(): array;

    /**
     * Give a login a role at one branch. With an actor, they must already hold every key of the
     * role there (no escalation); without one, the grant is the system's own.
     */
    public function grantAtBranch(int $userId, int $roleId, int $branchId, ?Authenticatable $actor): void;
}
