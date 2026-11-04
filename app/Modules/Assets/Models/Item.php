<?php

namespace App\Modules\Assets\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A stock item counted by quantity (glass cups, juice, cable…), as opposed to a tagged asset.
 * Its unit says how stock is counted; variants are the actual things on the shelf.
 */
#[Table('ast__items')]
#[Fillable(['category_id', 'unit_id', 'name_ar', 'name_en', 'description', 'min_stock', 'is_active'])]
class Item extends Model
{
    use BelongsToClient, HasBilingualFields;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['min_stock' => 'decimal:3', 'is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return HasMany<ItemVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ItemVariant::class);
    }

    /**
     * @return HasMany<ItemPack, $this>
     */
    public function packs(): HasMany
    {
        return $this->hasMany(ItemPack::class);
    }
}
