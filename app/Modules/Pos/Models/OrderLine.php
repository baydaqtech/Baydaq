<?php

namespace App\Modules\Pos\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * @property list<array{name: string, price: string}>|null $modifiers
 */
#[Table('pos__order_lines')]
class OrderLine extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'vat_rate' => 'decimal:2', 'net' => 'decimal:2', 'vat' => 'decimal:2', 'total' => 'decimal:2', 'modifiers' => 'array'];
    }
}
