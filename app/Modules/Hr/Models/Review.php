<?php

namespace App\Modules\Hr\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employee's review in a cycle: their own scores first, then the manager's, which decide.
 */
#[Table('hr__reviews')]
class Review extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'self_scores' => 'array', 'manager_scores' => 'array', 'overall' => 'decimal:2',
            'self_submitted_at' => 'datetime', 'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ReviewCycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ReviewCycle::class, 'cycle_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function status(): string
    {
        return match (true) {
            $this->reviewed_at !== null => 'done',
            $this->self_submitted_at !== null => 'manager',
            default => 'self',
        };
    }
}
