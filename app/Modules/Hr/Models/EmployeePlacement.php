<?php

namespace App\Modules\Hr\Models;

use App\Modules\Hr\Enums\PlacementKind;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Where an employee works over time. The open-ended "home" row is the current home branch;
 * secondment rows add a host branch for a period. Attendance asks: "is there a placement
 * covering branch X on date D?"
 */
#[Table('hr__employee_placements')]
class EmployeePlacement extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PlacementKind::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @param  Builder<EmployeePlacement>  $query
     */
    #[Scope]
    protected function coveringDate(Builder $query, Carbon $date): void
    {
        $query->whereDate('starts_on', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date));
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<EmployeeMovement, $this>
     */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(EmployeeMovement::class, 'movement_id');
    }
}
