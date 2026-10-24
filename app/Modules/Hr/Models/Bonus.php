<?php

namespace App\Modules\Hr\Models;

use App\Modules\Hr\Enums\AdjustmentStatus;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-off bonus for an employee in a pay month. Lives through pending → approved → paid (on a payroll line).
 */
#[Table('hr__bonuses')]
#[Fillable(['amount', 'pay_month', 'reason'])]
class Bonus extends Model
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
        return ['amount' => 'decimal:2', 'pay_month' => 'date', 'status' => AdjustmentStatus::class];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<BonusType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(BonusType::class, 'bonus_type_id');
    }
}
