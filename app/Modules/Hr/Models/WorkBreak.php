<?php

namespace App\Modules\Hr\Models;

use App\Modules\Hr\Enums\BreakKind;
use App\Modules\Hr\Enums\BreakStatus;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One break of one employee. ("Break" is a reserved word in PHP.) Changed by BreakFlow only.
 */
#[Table('hr__breaks')]
class WorkBreak extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => BreakKind::class, 'status' => BreakStatus::class, 'minutes' => 'integer', 'work_date' => 'date',
            'requested_at' => 'datetime', 'starts_at' => 'datetime', 'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** When the break should be over. */
    public function dueBackAt(): ?Carbon
    {
        return $this->starts_at?->copy()->addMinutes($this->minutes);
    }

    public function isOverdue(): bool
    {
        return $this->status === BreakStatus::OnBreak && $this->dueBackAt()?->isPast();
    }
}
