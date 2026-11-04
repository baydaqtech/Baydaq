<?php

namespace App\Modules\Hr\Actions\Movements;

use App\Modules\Hr\Enums\MovementStatus;
use App\Modules\Hr\Models\EmployeeMovement;
use App\Modules\Platform\Ports\Audit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

class CancelMovement
{
    public function __construct(private Audit $audit) {}

    /**
     * Cancel a movement that has not started. An active secondment is ended today instead.
     */
    public function handle(EmployeeMovement $movement, ?Authenticatable $actor = null): EmployeeMovement
    {
        if ($movement->status === MovementStatus::Scheduled) {
            $movement->forceFill(['status' => MovementStatus::Cancelled->value])->save();
        } elseif ($movement->status === MovementStatus::Active && $movement->type->value === 'secondment') {
            $movement->forceFill(['status' => MovementStatus::Completed->value, 'ends_on' => today()->toDateString()])->save();
            $movement->placements()->whereNull('ends_on')->orWhereDate('ends_on', '>', today())->update(['ends_on' => today()->toDateString()]);
        } else {
            throw ValidationException::withMessages(['movement' => __('hr.movements.errors.cannot_cancel')]);
        }

        $this->audit->record($movement, 'cancelled', null, $actor);

        return $movement;
    }
}
