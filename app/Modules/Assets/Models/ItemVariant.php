<?php

namespace App\Modules\Assets\Models;

use App\Models\Concerns\HasBilingualFields;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One kind of an item, with its own barcode and cost: "Mango 330 ml", "Milk 400 ml".
 */
#[Table('ast__item_variants')]
#[Fillable(['name_ar', 'name_en', 'sku', 'barcode', 'content', 'content_unit_id', 'unit_cost', 'is_active'])]
class ItemVariant extends Model
{
    use HasBilingualFields;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['unit_cost' => 0, 'is_active' => true];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['content' => 'decimal:3', 'unit_cost' => 'decimal:4', 'is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function contentUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'content_unit_id');
    }

    /** "Juice · Mango 330 ml" */
    public function label(): string
    {
        return $this->item->localized('name').' · '.$this->localized('name');
    }
}
