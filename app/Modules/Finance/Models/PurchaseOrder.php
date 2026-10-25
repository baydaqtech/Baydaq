<?php

namespace App\Modules\Finance\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An order to a supplier for a branch: drafted, approved, received, then billed.
 */
#[Table('finance__purchase_orders')]
class PurchaseOrder extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['order_date' => 'date', 'expected_on' => 'date', 'subtotal' => 'decimal:2', 'vat' => 'decimal:2', 'total' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<DocumentLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(DocumentLine::class, 'document_id')->where('document_type', 'purchase_order');
    }
}
