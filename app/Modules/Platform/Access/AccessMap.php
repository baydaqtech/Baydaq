<?php

namespace App\Modules\Platform\Access;

/**
 * Everything one user may do in the current tenant: each permission key with the part of
 * the tree it reaches. Built once per request by the resolver and used for backend checks,
 * query filters and the `can` flags sent to the UI.
 */
final class AccessMap
{
    /**
     * @param  array<string, BranchSet>  $grants
     * @param  list<string>  $allKeys  every key in the registry, which an owner holds in full
     */
    public function __construct(
        private readonly array $grants,
        private readonly bool $owner,
        private readonly array $allKeys,
    ) {}

    public static function empty(): self
    {
        return new self([], false, []);
    }

    public function isOwner(): bool
    {
        return $this->owner;
    }

    /** Holds the key anywhere in the tenant. */
    public function allows(string $key): bool
    {
        return $this->owner || ! $this->branches($key)->isEmpty();
    }

    public function allowsBranch(string $key, int $branchId, int $companyId): bool
    {
        return $this->branches($key)->allowsBranch($branchId, $companyId);
    }

    public function branches(string $key): BranchSet
    {
        if ($this->owner) {
            return BranchSet::all();
        }

        return $this->grants[$key] ?? BranchSet::none();
    }

    /**
     * Keys held anywhere, for the UI.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return $this->owner ? $this->allKeys : array_keys(array_filter($this->grants, fn (BranchSet $set): bool => ! $set->isEmpty()));
    }
}
