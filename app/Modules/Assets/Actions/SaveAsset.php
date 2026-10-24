<?php

namespace App\Modules\Assets\Actions;

use App\Modules\Assets\Models\Asset;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveAsset
{
    public function __construct(private CurrentTenant $tenant, private OrgDirectory $directory, private Audit $audit) {}

    /**
     * Register or update an asset. A new asset gets the owner's next tag (AST-00001). Moving it to
     * another branch keeps it in the same company; an asset held by an employee stays where it is.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Asset $asset, array $data, int $branchId, ?Authenticatable $actor = null): Asset
    {
        $branch = $this->directory->branch($branchId);

        if ($branch === null || ($asset->exists && $branch->companyId !== $asset->company_id)) {
            throw ValidationException::withMessages(['branch_id' => __('assets.errors.branch_company')]);
        }

        return DB::transaction(function () use ($asset, $data, $branch, $actor): Asset {
            $moved = $asset->exists && $asset->branch_id !== $branch->id;
            $asset->fill($data);

            if (! $asset->exists) {
                $clientId = $this->tenant->client()->id;
                $next = (int) Asset::query()->lockForUpdate()->count() + 1;
                $asset->forceFill(['client_id' => $clientId, 'company_id' => $branch->companyId, 'tag' => 'AST-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT)]);
            }

            $asset->forceFill(['branch_id' => $branch->id])->save();
            $this->audit->record($asset, $asset->wasRecentlyCreated ? 'registered' : ($moved ? 'moved' : 'updated'), $moved ? ['branch_id' => $branch->id] : null, $actor);

            return $asset;
        });
    }
}
