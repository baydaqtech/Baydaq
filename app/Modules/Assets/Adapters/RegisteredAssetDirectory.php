<?php

namespace App\Modules\Assets\Adapters;

use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Ports\AssetDirectory;

class RegisteredAssetDirectory implements AssetDirectory
{
    public function forCompany(int $companyId): array
    {
        return Asset::query()->where('company_id', $companyId)->whereNotIn('status', ['lost', 'disposed'])->orderBy('tag')->get()
            ->map(fn (Asset $asset): array => self::row($asset))->all();
    }

    public function find(int $id): ?array
    {
        $asset = Asset::query()->find($id);

        return $asset ? self::row($asset) : null;
    }

    /**
     * @return array{id: int, tag: string, name: string, branch_id: int}
     */
    private static function row(Asset $asset): array
    {
        return ['id' => $asset->id, 'tag' => $asset->tag, 'name' => $asset->localized('name'), 'branch_id' => $asset->branch_id];
    }
}
