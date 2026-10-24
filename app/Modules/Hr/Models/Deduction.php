<?php

namespace App\Modules\Hr\Models;

use App\Modules\Hr\Enums\AdjustmentStatus;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A deduction from an employee's pay in a month, within the legal caps. Lives through pending → approved → paid (on a payroll line).
 */
#[Table('hr__deductions')]
#[Fillable(['amount', 'pay_month', 'reason'])]
class Deduction extends Model
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
     * @return BelongsTo<DeductionType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(DeductionType::class, 'deduction_type_id');
    }
}
