<?php

namespace App\Modules\Hr\Models;

use App\Modules\Hr\Enums\AdvanceStatus;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A salary advance (سلفة): requested, approved, disbursed, then repaid by installments through
 * payroll, by hand, or settled early. Settled when the balance reaches zero.
 */
#[Table('hr__advances')]
#[Fillable(['amount', 'installments', 'first_installment_month', 'reason'])]
class Advance extends Model
{
    use BelongsToClient;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'installments' => 'integer',
            'first_installment_month' => 'date',
            'disbursed_on' => 'date',
            'status' => AdvanceStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<AdvanceInstallment, $this>
     */
    public function schedule(): HasMany
    {
        return $this->hasMany(AdvanceInstallment::class)->orderBy('due_month');
    }

    /**
     * @return HasMany<AdvanceRepayment, $this>
     */
    public function repayments(): HasMany
    {
        return $this->hasMany(AdvanceRepayment::class)->orderBy('paid_on');
    }

    public function balance(): string
    {
        return bcsub((string) $this->amount, (string) $this->repayments()->sum('amount'), 2);
    }
}
