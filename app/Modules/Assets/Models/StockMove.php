<?php

namespace App\Modules\Assets\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the stock ledger. A null side means outside the books (a purchase came in, an
 * incident took it out).
 */
#[Table('ast__stock_moves')]
class StockMove extends Model
{
    use BelongsToClient;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'moved_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<ItemVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class);
    }
}
