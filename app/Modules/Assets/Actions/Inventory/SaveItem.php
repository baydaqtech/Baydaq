<?php

namespace App\Modules\Assets\Actions\Inventory;

use App\Modules\Assets\Models\Item;
use App\Modules\Assets\Models\ItemVariant;
use App\Modules\Assets\Models\StockMove;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveItem
{
    public function __construct(private CurrentTenant $tenant) {}

    /**
     * Save an item with its variants and packs. Variants and packs not sent are removed (variants
     * only when they never moved; otherwise they are switched off). The stock unit cannot change
     * once the item has stock history, since every quantity is in that unit.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $variants
     * @param  list<array{id?: ?int, name_ar: string, name_en: string, factor: numeric-string|float|int}>  $packs
     */
    public function handle(Item $item, array $data, array $variants, array $packs = []): Item
    {
        if ($variants === []) {
            throw ValidationException::withMessages(['variants' => __('assets.errors.no_variants')]);
        }

        $history = $item->exists && StockMove::query()->whereIn('variant_id', $item->variants()->select('id'))->exists();

        if ($history && (int) $data['unit_id'] !== $item->unit_id) {
            throw ValidationException::withMessages(['unit_id' => __('assets.errors.unit_locked')]);
        }

        return DB::transaction(function () use ($item, $data, $variants, $packs): Item {
            $item->fill($data);
            $item->client_id ??= $this->tenant->client()->id;
            $item->save();

            $kept = [];

            foreach ($variants as $row) {
                $variant = ! empty($row['id']) ? $item->variants()->findOrFail($row['id']) : $item->variants()->make();
                $variant->fill(collect($row)->except('id')->all())->save();
                $kept[] = $variant->id;
            }

            $item->variants()->whereNotIn('id', $kept)->get()->each(fn (ItemVariant $variant) => StockMove::query()->where('variant_id', $variant->id)->exists()
                ? $variant->forceFill(['is_active' => false])->save()
                : $variant->delete());

            $item->packs()->delete();
            $item->packs()->createMany(array_map(fn (array $pack): array => collect($pack)->only(['name_ar', 'name_en', 'factor'])->all(), $packs));

            return $item->load(['variants', 'packs', 'unit']);
        });
    }
}
