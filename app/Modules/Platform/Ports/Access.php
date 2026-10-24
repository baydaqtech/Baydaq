<?php

namespace App\Modules\Platform\Ports;

use App\Models\User;
use App\Modules\Platform\Access\BranchSet;
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
}
