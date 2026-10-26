<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Actions\Suite\SaveWorkLocation;
use App\Modules\Platform\Enums\SuiteFeature;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Http\Requests\SaveWorkLocationRequest;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\WorkLocation;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

/**
 * Geofences for attendance, managed with the structure.
 */
class WorkLocationController extends Controller
{
    use AuthorizesSuite, RendersSuitePages;

    public function index(): Response
    {
        $reach = $this->access()->branches(SuiteFeature::Structure->key('view'));
        $branches = Branch::query()->with('company:id,name_ar,name_en')->get()->filter(fn (Branch $branch) => $reach->allowsBranch($branch->id, $branch->company_id));

        return $this->suitePage('Suite/Structure/Locations', [
            'locations' => WorkLocation::query()->with('branches:id')->orderBy('name_en')->get()->map(fn (WorkLocation $location): array => [
                'id' => $location->id,
                'name' => $location->localized('name'),
                'name_ar' => $location->name_ar,
                'name_en' => $location->name_en,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'radius_m' => $location->radius_m,
                'wifi_bssids' => $location->wifi_bssids ?? [],
                'is_active' => $location->is_active,
                'branch_ids' => $location->branches->pluck('id')->all(),
            ])->values()->all(),
            'branches' => $branches->map(fn (Branch $branch): array => ['value' => $branch->id, 'label' => $branch->company->localized('name').' — '.$branch->localized('name')])->values()->all(),
        ]);
    }

    public function store(SaveWorkLocationRequest $request, SaveWorkLocation $save): RedirectResponse
    {
        $this->authorizeAccount(SuiteFeature::Structure->key('create'));
        $save->handle(new WorkLocation, $request->safe()->except('branch_ids'), $request->validated('branch_ids'));
        $this->toast(__('suite.structure.saved'));

        return back();
    }

    public function update(SaveWorkLocationRequest $request, WorkLocation $location, SaveWorkLocation $save): RedirectResponse
    {
        $this->authorizeAccount(SuiteFeature::Structure->key('update'));
        $save->handle($location, $request->safe()->except('branch_ids'), $request->validated('branch_ids'));
        $this->toast(__('suite.structure.saved'));

        return back();
    }
}
