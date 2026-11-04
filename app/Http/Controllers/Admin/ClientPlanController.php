<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RendersAdminPages;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Modules\Platform\Access\FeatureCatalog;
use App\Modules\Platform\Actions\Plans\ApplyPlan;
use App\Modules\Platform\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Put a client (an owner's account, with all its organizations, companies and employees) on a
 * package, with add-ons and custom overrides.
 */
class ClientPlanController extends Controller
{
    use RendersAdminPages;

    public function store(Request $request, Client $client, ApplyPlan $apply): RedirectResponse
    {
        $data = $request->validate([
            'package_id' => ['required', Rule::exists('platform__packages', 'id')],
            'status' => ['required', 'in:trial,active,suspended'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'addons' => ['array'],
            'addons.*.addon_id' => ['required', Rule::exists('platform__addons', 'id')],
            'addons.*.quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'overrides.limits' => ['array'], 'overrides.limits.*' => ['nullable', 'integer', 'min:0'],
            'overrides.features' => ['array'], 'overrides.features.*' => ['boolean'],
            'overrides.mobile' => ['array'], 'overrides.mobile.*' => ['boolean'],
        ]);

        $overrides = [
            'limits' => array_intersect_key($data['overrides']['limits'] ?? [], array_flip(FeatureCatalog::LIMITS)),
            'features' => array_intersect_key($data['overrides']['features'] ?? [], array_flip(FeatureCatalog::features())),
            'mobile' => array_intersect_key($data['overrides']['mobile'] ?? [], array_flip(FeatureCatalog::MOBILE)),
        ];

        $apply->handle($client, Package::query()->findOrFail($data['package_id']), array_values($data['addons'] ?? []), array_filter($overrides), $data['status'], $data['starts_on'], $data['ends_on'] ?? null, $data['billing_cycle']);
        $this->toast(__('admin.plans.applied'));

        return back();
    }
}
