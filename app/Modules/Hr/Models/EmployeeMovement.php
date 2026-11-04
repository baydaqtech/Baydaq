<?php

namespace App\Modules\Hr\Models;

use App\Modules\Hr\Enums\MovementStatus;
use App\Modules\Hr\Enums\MovementType;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The business event behind a placement: a transfer or a secondment, scheduled, then applied
 * on its start date by hr:apply-movements.
 */
#[Table('hr__employee_movements')]
#[Fillable(['starts_on', 'ends_on', 'reason'])]
class EmployeeMovement extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'status' => MovementStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
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
     * @return HasMany<EmployeePlacement, $this>
     */
    public function placements(): HasMany
    {
        return $this->hasMany(EmployeePlacement::class, 'movement_id');
    }
}
