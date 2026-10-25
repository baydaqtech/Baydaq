<?php

namespace App\Modules\Hr\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An exit/re-entry or final exit visa issued through Muqeem, tracked from issue to return.
 */
#[Table('hr__travel_visas')]
#[Fillable(['employee_id', 'kind', 'number', 'issued_on', 'expires_on', 'duration_days', 'notes'])]
class TravelVisa extends Model
{
    use BelongsToClient;

    public const KINDS = ['single', 'multiple', 'final_exit'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['issued_on' => 'date', 'expires_on' => 'date', 'departed_on' => 'date', 'returned_on' => 'date'];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** The day the employee must be back: the stay allowed abroad, counted from departure. */
    public function returnBy(): ?CarbonInterface
    {
        if ($this->kind === 'final_exit' || $this->departed_on === null || $this->duration_days === null) {
            return null;
        }

        return $this->departed_on->copy()->addDays($this->duration_days);
    }

    /**
     * What needs attention: issued but past its leave-by date, or abroad past the return date.
     */
    public function alert(): ?string
    {
        if ($this->status === 'issued' && $this->expires_on->isPast() && ! $this->expires_on->isToday()) {
            return 'unused';
        }

        if ($this->status === 'abroad' && $this->returnBy()?->lt(today())) {
            return 'overdue';
        }

        return null;
    }
}
