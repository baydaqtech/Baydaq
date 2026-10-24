<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Pos\Models\Product;
use Illuminate\Support\Facades\DB;

class SaveProduct
{
    public function __construct(private CurrentTenant $tenant) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $modifierGroupIds
     * @param  list<array{branch_id: int, price?: ?string, is_available?: bool}>  $branchOverrides
     */
    public function handle(Product $product, array $data, array $modifierGroupIds = [], array $branchOverrides = []): Product
    {
        return DB::transaction(function () use ($product, $data, $modifierGroupIds, $branchOverrides): Product {
            $product->fill($data);

            if (! $product->exists && empty($product->client_id)) {
                $product->client_id = $this->tenant->client()->id;
            }

            $product->save();
            $product->modifierGroups()->sync($modifierGroupIds);

            $product->getConnection()->table('pos__branch_products')->where('product_id', $product->id)->delete();
            $product->getConnection()->table('pos__branch_products')->insert(array_map(fn (array $row): array => [
                'product_id' => $product->id, 'branch_id' => (int) $row['branch_id'], 'price' => $row['price'] ?? null, 'is_available' => (bool) ($row['is_available'] ?? true),
            ], $branchOverrides));

            return $product->load(['category', 'modifierGroups.modifiers']);
        });
    }
}
