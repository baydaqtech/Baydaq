<?php

namespace App\Modules\Assets\Models;

use App\Modules\Assets\Enums\HolderType;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How much of a variant a holder has: a warehouse, an employee, or a transfer/handover on its way.
 */
#[Table('ast__stock')]
class Stock extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['holder_type' => HolderType::class, 'quantity' => 'decimal:3'];
    }

    /**
     * @return BelongsTo<ItemVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class);
    }
}
