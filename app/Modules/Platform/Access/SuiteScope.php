<?php

namespace App\Modules\Platform\Access;

use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Organization;

/**
 * Where the user is looking right now: the whole account, one organization or one company,
 * optionally narrowed to some branches (?branches=3,4). Every suite query asks it which
 * branches a permission reaches inside this view.
 */
final class SuiteScope
{
    /**
     * @param  'all'|'organization'|'company'  $level
     * @param  list<int>  $selectedBranchIds  branches picked in the UI, already limited to the node
     * @param  array<int, int>  $branchCompanies  branch id → company id for every branch in the node
     */
    public function __construct(
        public readonly string $level,
        public readonly ?Organization $organization,
        public readonly ?Company $company,
        public readonly array $selectedBranchIds,
        public readonly array $branchCompanies,
        public readonly BranchSet $node,
    ) {}

    /** The URL segment for this scope: all, o-{slug} or c-{slug}. */
    public function segment(): string
    {
        return match ($this->level) {
            'organization' => 'o-'.$this->organization->slug,
            'company' => 'c-'.$this->company->slug,
            default => 'all',
        };
    }

    /**
     * The branches a permission reaches inside this view: the node, narrowed by the branch
     * filter, intersected with what the user's grants allow.
     */
    public function branchesFor(string $key, AccessMap $access): BranchSet
    {
        $view = $this->selectedBranchIds === [] ? $this->node : BranchSet::of([], $this->selectedBranchIds);

        return $view->intersect($access->branches($key), $this->branchCompanies);
    }

    public function allowsBranch(string $key, AccessMap $access, Branch|int $branch, ?int $companyId = null): bool
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;
        $companyId ??= $branch instanceof Branch ? $branch->company_id : ($this->branchCompanies[$branchId] ?? 0);

        return $this->branchesFor($key, $access)->allowsBranch($branchId, $companyId);
    }
}
