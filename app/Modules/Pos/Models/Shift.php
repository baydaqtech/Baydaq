<?php

namespace App\Modules\Pos\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cash session on a register: opened with a float, closed with a count; the difference is
 * the shortage or surplus HR may later turn into a deduction.
 */
#[Table('pos__shifts')]
class Shift extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'closed_at' => 'datetime', 'opening_cash' => 'decimal:2', 'expected_cash' => 'decimal:2', 'counted_cash' => 'decimal:2', 'difference' => 'decimal:2', 'sales_total' => 'decimal:2', 'shortage_sent' => 'boolean'];
    }

    /**
     * @return BelongsTo<Register, $this>
     */
    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    /**
     * @return BelongsTo<Cashier, $this>
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(Cashier::class);
    }
}
