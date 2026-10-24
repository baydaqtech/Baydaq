<?php

namespace App\Modules\Assets\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of a transfer or handover: a quantity of a variant, or one tagged asset.
 */
#[Table('ast__movement_lines')]
class MovementLine extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'received' => 'decimal:3'];
    }

    /**
     * @return BelongsTo<ItemVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class);
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function label(): string
    {
        return $this->asset ? $this->asset->tag.' '.$this->asset->localized('name') : $this->variant->label();
    }
}
