<?php

namespace App\Modules\Invoicing\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * One line of an invoice; the money columns are computed by SaveInvoice.
 */
#[Table('inv__invoice_lines')]
class InvoiceLine extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3', 'unit_price' => 'decimal:4', 'discount' => 'decimal:2', 'vat_rate' => 'decimal:2',
            'net' => 'decimal:2', 'vat' => 'decimal:2', 'total' => 'decimal:2',
        ];
    }
}
