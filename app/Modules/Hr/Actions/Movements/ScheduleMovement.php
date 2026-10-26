<?php

namespace App\Modules\Hr\Actions\Movements;

use App\Modules\Hr\Enums\MovementStatus;
use App\Modules\Hr\Enums\MovementType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeMovement;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleMovement
{
    public function __construct(private OrgDirectory $directory, private Audit $audit, private ApplyDueMovements $apply) {}

    /**
     * Schedule a transfer (new home branch, same company, from a date) or a secondment (host
     * branch for a period, possibly in another company of the owner). A movement that starts
     * today or earlier is applied at once.
     *
     * @param  array{to_branch_id: int, starts_on: string, ends_on?: ?string, reason?: ?string}  $data
     */
    public function handle(Employee $employee, MovementType $type, array $data, ?Authenticatable $actor = null): EmployeeMovement
    {
        $to = $this->directory->branch((int) $data['to_branch_id']) ?? throw ValidationException::withMessages(['to_branch_id' => __('hr.movements.errors.unknown_branch')]);
        $startsOn = Carbon::parse($data['starts_on'])->startOfDay();
        $endsOn = isset($data['ends_on']) && $data['ends_on'] ? Carbon::parse($data['ends_on'])->startOfDay() : null;

        if ($type === MovementType::Transfer) {
            if ($to->companyId !== $employee->company_id) {
                throw ValidationException::withMessages(['to_branch_id' => __('hr.movements.errors.transfer_same_company')]);
            }

            if ($to->id === $employee->branch_id) {
                throw ValidationException::withMessages(['to_branch_id' => __('hr.movements.errors.same_branch')]);
            }

            $endsOn = null;
        } else {
            if ($endsOn === null || $endsOn->lt($startsOn)) {
                throw ValidationException::withMessages(['ends_on' => __('hr.movements.errors.secondment_period')]);
            }

            $overlaps = $employee->movements()
                ->where('type', MovementType::Secondment)
                ->whereIn('status', [MovementStatus::Scheduled, MovementStatus::Active])
                ->whereDate('starts_on', '<=', $endsOn)
                ->whereDate('ends_on', '>=', $startsOn)
                ->exists();

            if ($overlaps) {
                throw ValidationException::withMessages(['starts_on' => __('hr.movements.errors.overlap')]);
            }
        }

        return DB::transaction(function () use ($employee, $type, $to, $startsOn, $endsOn, $data, $actor): EmployeeMovement {
            $movement = $employee->movements()->make(['reason' => $data['reason'] ?? null]);
            $movement->forceFill([
                'client_id' => $employee->client_id,
                'type' => $type->value,
                'status' => MovementStatus::Scheduled->value,
                'from_branch_id' => $employee->branch_id,
                'to_company_id' => $to->companyId,
                'to_branch_id' => $to->id,
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $endsOn?->toDateString(),
                'requested_by' => $actor?->getAuthIdentifier(),
            ])->save();

            $this->audit->record($movement, 'scheduled', ['type' => $type->value, 'to_branch_id' => $to->id, 'starts_on' => $startsOn->toDateString()], $actor);

            if ($startsOn->lte(today())) {
                $this->apply->handle(today(), $employee->client_id);
            }

            return $movement->refresh();
        });
    }
}
