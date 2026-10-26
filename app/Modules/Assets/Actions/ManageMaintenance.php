<?php

namespace App\Modules\Assets\Actions;

use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\Maintenance;
use App\Modules\Platform\Ports\Audit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageMaintenance
{
    public function __construct(private Audit $audit) {}

    /**
     * Open a job. The asset is out of service meanwhile; if an employee holds it, the custody
     * stays open.
     *
     * @param  array{kind: string, description: string, opened_on: string, vendor?: ?string, reported_by_employee_id?: ?int}  $data
     */
    public function open(Asset $asset, array $data, ?Authenticatable $actor = null): Maintenance
    {
        if (in_array($asset->status, [AssetStatus::Disposed, AssetStatus::Lost, AssetStatus::Maintenance], true)) {
            throw ValidationException::withMessages(['asset' => __('assets.errors.not_serviceable')]);
        }

        return DB::transaction(function () use ($asset, $data, $actor): Maintenance {
            $job = $asset->maintenance()->make()->forceFill([...$data, 'client_id' => $asset->client_id, 'opened_by' => $actor?->getAuthIdentifier()]);
            $job->save();
            $asset->forceFill(['status' => AssetStatus::Maintenance->value])->save();
            $this->audit->record($asset, 'maintenance_opened', ['kind' => $data['kind']], $actor);

            return $job;
        });
    }

    /** Close a job with its cost; the asset goes back to its holder or to the shelf. */
    public function close(Maintenance $job, string $on, ?string $cost = null, ?string $resolution = null, ?Authenticatable $actor = null): Maintenance
    {
        if ($job->closed_on !== null) {
            throw ValidationException::withMessages(['job' => __('assets.errors.already_closed')]);
        }

        return DB::transaction(function () use ($job, $on, $cost, $resolution, $actor): Maintenance {
            $job->forceFill(['closed_on' => $on, 'cost' => $cost, 'resolution' => $resolution])->save();
            $asset = $job->asset;
            $asset->forceFill(['status' => ($asset->openCustody()->exists() ? AssetStatus::Assigned : AssetStatus::Available)->value])->save();
            $this->audit->record($asset, 'maintenance_closed', ['cost' => $cost], $actor);

            return $job;
        });
    }
}
