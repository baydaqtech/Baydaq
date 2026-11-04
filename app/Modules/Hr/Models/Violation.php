<?php

namespace App\Modules\Hr\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One breach of the work rules, with the employee's answer and the penalty. Written by RecordViolation.
 */
#[Table('hr__violations')]
class Violation extends Model
{
    use BelongsToClient;

    public const CATEGORIES = ['late', 'absence', 'early_leave', 'break_overdue', 'location', 'conduct', 'safety', 'other'];

    public const PENALTIES = ['none', 'warning', 'written_warning', 'deduction', 'suspension', 'dismissal'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['occurred_on' => 'date', 'deduction_days' => 'decimal:2', 'notified_at' => 'datetime', 'responded_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
