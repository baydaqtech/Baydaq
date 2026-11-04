<?php

namespace App\Modules\Platform\Actions\Approvals;

use App\Models\User;
use App\Modules\Platform\Access\AccessResolver;
use App\Modules\Platform\Data\ApprovalItem;
use App\Modules\Platform\Data\ScopeContext;
use App\Modules\Platform\Ports\ApprovalSource;
use App\Modules\Platform\Ports\OrgDirectory;

/**
 * Everything waiting on one user, from every module's ApprovalSource, oldest first.
 */
class CollectApprovals
{
    public function __construct(private AccessResolver $resolver, private OrgDirectory $directory) {}

    /** @return list<ApprovalItem> */
    public function handle(ScopeContext $context, int $userId): array
    {
        $items = [];

        foreach (app()->tagged(ApprovalSource::TAG) as $source) {
            array_push($items, ...$source->waiting($context, $userId));
        }

        usort($items, fn (ApprovalItem $a, ApprovalItem $b): int => $a->since <=> $b->since);

        return $items;
    }

    /**
     * The same across the user's whole reach (no scope or branch filter), for the daily alert.
     *
     * @return list<ApprovalItem>
     */
    public function everywhere(User $user): array
    {
        $map = $this->resolver->for($user);

        return $this->handle(new ScopeContext(fn (string $key) => $map->branches($key), $this->directory->branchCompanies()), $user->id);
    }
}
