<?php

namespace App\Modules\Assets\Ports;

/**
 * Query port: assets other modules may point at (a POS register's hardware, P5).
 */
interface AssetDirectory
{
    /**
     * Assets of a company that are not written off, for pickers.
     *
     * @return list<array{id: int, tag: string, name: string, branch_id: int}>
     */
    public function forCompany(int $companyId): array;

    /** @return array{id: int, tag: string, name: string, branch_id: int}|null */
    public function find(int $id): ?array;
}
