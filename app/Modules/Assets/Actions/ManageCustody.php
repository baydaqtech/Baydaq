<?php

namespace App\Modules\Assets\Actions;

use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\Custody;
use App\Modules\Hr\Ports\StaffDirectory;
use App\Modules\Platform\Ports\Audit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageCustody
{
    public function __construct(private StaffDirectory $staff, private Audit $audit) {}

    /** Hand an available asset to an active employee of the asset's company. */
    public function assign(Asset $asset, int $employeeId, string $on, ?string $condition = null, ?string $note = null, ?Authenticatable $actor = null): Custody
    {
        $employee = $this->staff->employee($employeeId);

        if ($employee === null || $employee->companyId !== $asset->company_id || $employee->status !== 'active') {
            throw ValidationException::withMessages(['employee_id' => __('assets.errors.employee')]);
        }

        if ($asset->status !== AssetStatus::Available) {
            throw ValidationException::withMessages(['asset' => __('assets.errors.not_available')]);
        }

        return DB::transaction(function () use ($asset, $employeeId, $on, $condition, $note, $actor): Custody {
            $custody = $asset->custodies()->make()->forceFill([
                'client_id' => $asset->client_id, 'employee_id' => $employeeId, 'assigned_on' => $on, 'condition_out' => $condition, 'note' => $note, 'assigned_by' => $actor?->getAuthIdentifier(),
            ]);
            $custody->save();
            $asset->forceFill(['status' => AssetStatus::Assigned->value])->save();
            $this->audit->record($asset, 'assigned', ['employee_id' => $employeeId], $actor);

            return $custody;
        });
    }

    /** Take an asset back; it is available again (unless it is in maintenance). */
    public function return(Custody $custody, string $on, ?string $condition = null, ?Authenticatable $actor = null): Custody
    {
        if ($custody->returned_on !== null) {
            throw ValidationException::withMessages(['custody' => __('assets.errors.already_returned')]);
        }

        if ($on < $custody->assigned_on->toDateString()) {
            throw ValidationException::withMessages(['returned_on' => __('assets.errors.return_date')]);
        }

        return DB::transaction(function () use ($custody, $on, $condition, $actor): Custody {
            $custody->forceFill(['returned_on' => $on, 'condition_in' => $condition, 'returned_by' => $actor?->getAuthIdentifier()])->save();
            $asset = $custody->asset;

            if ($asset->status === AssetStatus::Assigned) {
                $asset->forceFill(['status' => AssetStatus::Available->value])->save();
            }

            $this->audit->record($asset, 'returned', ['employee_id' => $custody->employee_id, 'condition' => $condition], $actor);

            return $custody;
        });
    }
}
