<?php

namespace App\Modules\Hr\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Http\Controllers\Api\Concerns\ResolvesEmployee;
use App\Modules\Platform\Data\WorkLocationRef;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    use ResolvesEmployee;

    /**
     * Every geofence of the owner, so the phone can register the nearest ones for background
     * monitoring (iOS watches at most 20). Which branch a punch belongs to is decided server-side.
     */
    public function index(Request $request, OrgDirectory $directory): JsonResponse
    {
        $this->employee($request);

        return response()->json(['locations' => array_map(fn (WorkLocationRef $location): array => [
            'id' => $location->id,
            'name' => $location->name,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'radius_m' => $location->radiusM,
            'branch_ids' => $location->branchIds,
        ], $directory->workLocations())]);
    }
}
