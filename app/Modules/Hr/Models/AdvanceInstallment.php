<?php

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One month of an advance's repayment plan.
 */
#[Table('hr__advance_installments')]
class AdvanceInstallment extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['due_month' => 'date', 'amount' => 'decimal:2', 'paid' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Advance, $this>
     */
    public function advance(): BelongsTo
    {
        return $this->belongsTo(Advance::class);
    }

    public function remaining(): string
    {
        return bcsub((string) $this->amount, (string) $this->paid, 2);
    }
}
