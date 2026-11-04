<?php

namespace App\Modules\Pos\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use App\Modules\Pos\Enums\RefundMethod;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Money given back on a paid order, for some or all of its lines. Written by RefundOrder only.
 */
#[Table('pos__refunds')]
class Refund extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['method' => RefundMethod::class, 'subtotal' => 'decimal:2', 'vat' => 'decimal:2', 'total' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return HasMany<RefundLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(RefundLine::class);
    }
}
