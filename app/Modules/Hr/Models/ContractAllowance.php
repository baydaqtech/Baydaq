<?php

namespace App\Modules\Hr\Models;

use App\Modules\Hr\Enums\AllowanceCalculation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One allowance line of a contract, with the amount resolved at save time.
 */
#[Table('hr__contract_allowances')]
class ContractAllowance extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'calculation' => AllowanceCalculation::class,
            'value' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<AllowanceType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(AllowanceType::class, 'allowance_type_id');
    }
}
