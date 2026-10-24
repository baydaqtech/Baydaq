<?php

namespace App\Modules\Assets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assets\Actions\ManageMaintenance;
use App\Modules\Assets\Enums\AssetsFeature;
use App\Modules\Assets\Enums\MaintenanceKind;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\Maintenance;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\ReachesBranches;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaintenanceController extends Controller
{
    use ReachesBranches, RendersSuitePages;

    public function store(Request $request, Asset $asset, ManageMaintenance $maintenance): RedirectResponse
    {
        abort_unless($this->access()->allowsBranch(AssetsFeature::Maintenance->key(FeatureAction::Create), $asset->branch_id, $asset->company_id), 403);
        $data = $request->validate([
            'kind' => ['required', Rule::enum(MaintenanceKind::class)], 'description' => ['required', 'string', 'max:255'], 'opened_on' => ['required', 'date'],
            'vendor' => ['nullable', 'string', 'max:255'], 'reported_by_employee_id' => ['nullable', 'integer'],
        ]);

        $maintenance->open($asset, $data, $request->user());
        $this->toast(__('assets.maintenance.opened'));

        return back();
    }

    public function close(Request $request, Maintenance $job, ManageMaintenance $maintenance): RedirectResponse
    {
        $asset = $job->asset;
        abort_unless($this->access()->allowsBranch(AssetsFeature::Maintenance->key(FeatureAction::Update), $asset->branch_id, $asset->company_id), 403);
        $data = $request->validate(['closed_on' => ['required', 'date', 'after_or_equal:'.$job->opened_on->toDateString()], 'cost' => ['nullable', 'numeric', 'min:0'], 'resolution' => ['nullable', 'string', 'max:255']]);

        $maintenance->close($job, $data['closed_on'], isset($data['cost']) ? (string) $data['cost'] : null, $data['resolution'] ?? null, $request->user());
        $this->toast(__('assets.maintenance.closed'));

        return back();
    }
}
