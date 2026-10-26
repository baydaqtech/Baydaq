<?php

namespace App\Modules\Platform\Access;

/**
 * The part of a tenant's tree a grant reaches: the whole client, some companies (every
 * branch they have now or later), and/or some single branches. Short-circuits keep
 * queries free of long id lists.
 */
final class BranchSet
{
    /**
     * @param  list<int>  $companyIds
     * @param  list<int>  $branchIds
     */
    private function __construct(
        public readonly bool $all,
        public readonly array $companyIds,
        public readonly array $branchIds,
    ) {}

    public static function all(): self
    {
        return new self(true, [], []);
    }

    public static function none(): self
    {
        return new self(false, [], []);
    }

    /**
     * @param  list<int>  $companyIds
     * @param  list<int>  $branchIds
     */
    public static function of(array $companyIds = [], array $branchIds = []): self
    {
        return new self(false, self::ids($companyIds), self::ids($branchIds));
    }

    public function merge(self $other): self
    {
        if ($this->all || $other->all) {
            return self::all();
        }

        return self::of([...$this->companyIds, ...$other->companyIds], [...$this->branchIds, ...$other->branchIds]);
    }

    /**
     * What both sets reach. $branchCompanies maps every branch id mentioned by either set
     * to its company id, so a single branch can be matched against a company-wide grant.
     *
     * @param  array<int, int>  $branchCompanies
     */
    public function intersect(self $other, array $branchCompanies): self
    {
        if ($this->all) {
            return $other;
        }

        if ($other->all) {
            return $this;
        }

        $companies = array_values(array_intersect($this->companyIds, $other->companyIds));
        $branches = [];

        foreach ($this->branchIds as $branchId) {
            if ($other->allowsBranch($branchId, $branchCompanies[$branchId] ?? 0)) {
                $branches[] = $branchId;
            }
        }

        foreach ($other->branchIds as $branchId) {
            if ($this->allowsBranch($branchId, $branchCompanies[$branchId] ?? 0)) {
                $branches[] = $branchId;
            }
        }

        return self::of($companies, $branches);
    }

    public function isEmpty(): bool
    {
        return ! $this->all && $this->companyIds === [] && $this->branchIds === [];
    }

    public function allowsBranch(int $branchId, int $companyId): bool
    {
        return $this->all
            || in_array($companyId, $this->companyIds, true)
            || in_array($branchId, $this->branchIds, true);
    }

    public function allowsWholeCompany(int $companyId): bool
    {
        return $this->all || in_array($companyId, $this->companyIds, true);
    }

    /**
     * Constrain a query over rows that carry company_id and branch_id.
     *
     * @template TBuilder of \Illuminate\Contracts\Database\Query\Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public function apply(mixed $query, string $companyColumn = 'company_id', string $branchColumn = 'branch_id'): mixed
    {
        if ($this->all) {
            return $query;
        }

        if ($this->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($query) use ($companyColumn, $branchColumn): void {
            if ($this->companyIds !== []) {
                $query->whereIn($companyColumn, $this->companyIds);
            }

            if ($this->branchIds !== []) {
                $this->companyIds === []
                    ? $query->whereIn($branchColumn, $this->branchIds)
                    : $query->orWhereIn($branchColumn, $this->branchIds);
            }
        });
    }

    /**
     * @param  array<int|string>  $ids
     * @return list<int>
     */
    private static function ids(array $ids): array
    {
        return array_values(array_unique(array_map('intval', $ids)));
    }
}
