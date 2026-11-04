<?php

namespace App\Modules\Platform\Data;

use App\Modules\Platform\Access\BranchSet;
use Closure;

/**
 * What a cross-module read (search, the board) may look at: the branches each permission key
 * reaches in the current scope, already narrowed by the branch filter.
 */
final readonly class ScopeContext
{
    /**
     * @param  Closure(string): BranchSet  $reach
     * @param  array<int, int>  $branchCompanies  branch id → company id in the current scope
     */
    public function __construct(private Closure $reach, public array $branchCompanies) {}

    public function reach(string $key): BranchSet
    {
        return ($this->reach)($key);
    }

    /**
     * Companies where the key reaches at least one branch, for company-level records.
     *
     * @return list<int>
     */
    public function companiesReached(string $key): array
    {
        $reach = $this->reach($key);

        return array_values(array_unique(array_filter(
            $this->branchCompanies,
            fn (int $companyId, int $branchId): bool => $reach->allowsBranch($branchId, $companyId),
            ARRAY_FILTER_USE_BOTH,
        )));
    }
}
