<?php

namespace App\Modules\Pos\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A sellable item of a company. Price includes VAT (what the menu shows); branches may
 * override price or availability. One record feeds the POS, the QR menu and the invoice.
 *
 * @property list<string>|null $allergens
 */
#[Table('pos__products')]
#[Fillable([
    'company_id', 'category_id', 'sku', 'name_ar', 'name_en', 'description_ar', 'description_en', 'price', 'vat_rate', 'image',
    'calories', 'high_salt', 'caffeine_mg', 'allergens', 'is_active', 'sold_by_weight', 'sort_order',
])]
class Product extends Model
{
    use BelongsToClient, HasBilingualFields;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['vat_rate' => '15.00', 'is_active' => true, 'high_salt' => false, 'sold_by_weight' => false, 'sort_order' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'vat_rate' => 'decimal:2', 'calories' => 'integer', 'high_salt' => 'boolean', 'caffeine_mg' => 'integer', 'allergens' => 'array', 'is_active' => 'boolean', 'sold_by_weight' => 'boolean'];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsToMany<ModifierGroup, $this>
     */
    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class, 'pos__product_modifier_group', 'product_id', 'group_id');
    }

    /** Price and availability at a branch (override or the company default). @return array{price: string, available: bool} */
    public function atBranch(int $branchId): array
    {
        $override = $this->getConnection()->table('pos__branch_products')->where('product_id', $this->id)->where('branch_id', $branchId)->first();

        return ['price' => (string) ($override?->price ?? $this->price), 'available' => $this->is_active && ($override === null || (bool) $override->is_available)];
    }
}
