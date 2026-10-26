<?php

namespace App\Modules\Finance\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A supplier's invoice to pay: approved into the books (expense, input VAT, payable), then paid.
 */
#[Table('finance__bills')]
class Bill extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['bill_date' => 'date', 'due_on' => 'date', 'subtotal' => 'decimal:2', 'vat' => 'decimal:2', 'total' => 'decimal:2', 'paid' => 'decimal:2'];
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
        return $this->hasMany(DocumentLine::class, 'document_id')->where('document_type', 'bill');
    }

    /**
     * @return HasMany<BillPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(BillPayment::class);
    }

    public function balance(): string
    {
        return (string) BigDecimal::of((string) $this->total)->minus((string) $this->paid);
    }
}
