<?php

namespace App\Modules\Hr\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money an employee spent for the company and asks back, with the receipt.
 */
#[Table('hr__expense_claims')]
#[Fillable(['spent_on', 'category', 'amount', 'vat', 'merchant', 'merchant_vat_number', 'description'])]
class ExpenseClaim extends Model
{
    use BelongsToClient;

    /** Each category is also the ledger account it is booked to. */
    public const CATEGORIES = ['travel', 'fuel', 'phone', 'meals', 'supplies', 'maintenance', 'other_expense'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['spent_on' => 'date', 'amount' => 'decimal:2', 'vat' => 'decimal:2', 'decided_at' => 'datetime', 'paid_on' => 'date'];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
