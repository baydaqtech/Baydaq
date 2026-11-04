<?php

namespace App\Modules\Platform\Actions\Suite;

use App\Modules\Platform\Actions\Audit\RecordAuditEvent;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\WorkLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveWorkLocation
{
    public function __construct(private RecordAuditEvent $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $branchIds  branches this geofence serves (at least one, all of this tenant)
     */
    public function handle(WorkLocation $location, array $data, array $branchIds): WorkLocation
    {
        $branchIds = array_values(array_unique(array_map('intval', $branchIds)));

        if ($branchIds === [] || Branch::query()->whereIn('id', $branchIds)->count() !== count($branchIds)) {
            throw ValidationException::withMessages(['branch_ids' => __('suite.structure.errors.location_branches')]);
        }

        return DB::transaction(function () use ($location, $data, $branchIds): WorkLocation {
            $location->fill($data)->save();
            $location->branches()->sync($branchIds);
            $this->audit->handle($location, $location->wasRecentlyCreated ? 'created' : 'updated', ['branch_ids' => $branchIds]);

            return $location->load('branches');
        });
    }
}
