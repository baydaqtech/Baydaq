<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of a purchase order or a supplier bill: what, how many, at what price, and which
 * expense account it lands on.
 */
#[Table('finance__document_lines')]
class DocumentLine extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'vat_rate' => 'decimal:2', 'net' => 'decimal:2', 'vat' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
