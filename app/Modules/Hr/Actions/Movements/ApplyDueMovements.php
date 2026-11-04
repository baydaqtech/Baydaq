<?php

namespace App\Modules\Hr\Actions\Movements;

use App\Modules\Hr\Adapters\EmployeeStaffDirectory;
use App\Modules\Hr\Enums\MovementStatus;
use App\Modules\Hr\Enums\MovementType;
use App\Modules\Hr\Enums\PlacementKind;
use App\Modules\Hr\Events\EmployeeSeconded;
use App\Modules\Hr\Events\EmployeeTransferred;
use App\Modules\Hr\Models\EmployeeMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ApplyDueMovements
{
    /**
     * Start every scheduled movement whose date has come and complete every secondment whose
     * period is over. A transfer closes the home placement and opens a new one at the new
     * branch, then rewrites the employee's home branch; a secondment adds a host placement.
     * Runs daily per tenant (the caller sets the tenant) and after scheduling for today.
     */
    public function handle(Carbon $today, ?int $clientId = null): int
    {
        $applied = 0;

        $due = EmployeeMovement::query()->with('employee')
            ->when($clientId, fn ($query) => $query->where('client_id', $clientId))
            ->where('status', MovementStatus::Scheduled)->whereDate('starts_on', '<=', $today)
            ->orderBy('starts_on')->get();

        foreach ($due as $movement) {
            DB::transaction(function () use ($movement, &$applied): void {
                $employee = $movement->employee;

                if ($movement->type === MovementType::Transfer) {
                    $employee->placements()->where('kind', PlacementKind::Home)->whereNull('ends_on')
                        ->update(['ends_on' => $movement->starts_on->copy()->subDay()->toDateString()]);
                    $this->place($movement, PlacementKind::Home);
                    $employee->forceFill(['branch_id' => $movement->to_branch_id])->save();
                    $movement->forceFill(['status' => MovementStatus::Completed->value])->save();
                    EmployeeTransferred::dispatch(EmployeeStaffDirectory::summary($employee->refresh()));
                } else {
                    $this->place($movement, PlacementKind::Secondment);
                    $movement->forceFill(['status' => MovementStatus::Active->value])->save();
                    EmployeeSeconded::dispatch(EmployeeStaffDirectory::summary($employee));
                }

                $applied++;
            });
        }

        EmployeeMovement::query()
            ->when($clientId, fn ($query) => $query->where('client_id', $clientId))
            ->where('status', MovementStatus::Active)->whereDate('ends_on', '<', $today)
            ->update(['status' => MovementStatus::Completed->value]);

        return $applied;
    }

    private function place(EmployeeMovement $movement, PlacementKind $kind): void
    {
        $movement->employee->placements()->make()->forceFill([
            'client_id' => $movement->client_id,
            'movement_id' => $movement->id,
            'company_id' => $movement->to_company_id,
            'branch_id' => $movement->to_branch_id,
            'kind' => $kind->value,
            'starts_on' => $movement->starts_on->toDateString(),
            'ends_on' => $movement->ends_on?->toDateString(),
        ])->save();
    }
}
