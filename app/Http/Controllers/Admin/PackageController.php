<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RendersAdminPages;
use App\Http\Controllers\Controller;
use App\Modules\Platform\Access\FeatureCatalog;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Models\Addon;
use App\Modules\Platform\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * The packages and add-ons Baydaq sells: which modules, web features and employee-app screens
 * each one includes, and its limits.
 */
class PackageController extends Controller
{
    use RendersAdminPages;

    public function index(): Response
    {
        $row = fn (Package|Addon $item): array => [
            'id' => $item->id, 'code' => $item->code, 'name' => $item->localized('name'), 'price_monthly' => $item->price_monthly,
            'modules' => $item->modules, 'features' => count($item->features ?? []), 'mobile' => count($item->mobile ?? []),
            'limits' => $item->limits, 'is_active' => $item->is_active, 'is_public' => $item instanceof Package ? $item->is_public : null,
        ];

        return $this->adminPage('Admin/Packages/Index', [
            'packages' => Package::query()->orderBy('sort_order')->orderBy('id')->get()->map($row)->all(),
            'addons' => Addon::query()->orderBy('id')->get()->map($row)->all(),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form($request->query('kind') === 'addon' ? 'addon' : 'package', null);
    }

    public function edit(string $kind, int $id): Response
    {
        return $this->form($kind, ($kind === 'addon' ? Addon::query() : Package::query())->findOrFail($id));
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        $model = $kind === 'addon' ? new Addon : new Package;
        $model->fill($this->validated($request, $kind, null))->save();
        $this->toast(__('admin.common.saved'));

        return to_route('admin.packages.index');
    }

    public function update(Request $request, string $kind, int $id): RedirectResponse
    {
        $model = ($kind === 'addon' ? Addon::query() : Package::query())->findOrFail($id);
        $model->fill($this->validated($request, $kind, $model->id))->save();
        $this->toast(__('admin.common.saved'));

        return to_route('admin.packages.index');
    }

    private function form(string $kind, Package|Addon|null $item): Response
    {
        return $this->adminPage('Admin/Packages/Form', [
            'kind' => $kind,
            'item' => $item?->only(['id', 'code', 'name_ar', 'name_en', 'description_ar', 'description_en', 'price_monthly', 'price_yearly', 'trial_days', 'modules', 'features', 'mobile', 'limits', 'is_public', 'is_active', 'sort_order']),
            'catalog' => [...FeatureCatalog::options(), 'modules' => Module::options()],
            'copy' => ['admin' => trans('admin'), 'suite' => ['catalog' => trans('suite.catalog'), 'module' => trans('suite.module')]],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $kind, ?int $id): array
    {
        $table = $kind === 'addon' ? 'platform__addons' : 'platform__packages';
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'alpha_dash:ascii', Rule::unique($table, 'code')->ignore($id)],
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'price_yearly' => ['nullable', 'numeric', 'min:0'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'modules' => ['array'], 'modules.*' => [Rule::enum(Module::class)],
            'features' => ['array'], 'features.*' => [Rule::in(FeatureCatalog::features())],
            'mobile' => ['array'], 'mobile.*' => [Rule::in(FeatureCatalog::MOBILE)],
            'limits' => ['array'], 'limits.*' => ['nullable', 'integer', 'min:0'],
            'is_public' => ['boolean'], 'is_active' => ['boolean'], 'sort_order' => ['nullable', 'integer'],
        ]);

        $data['limits'] = array_intersect_key(array_map(fn ($value) => $value === null || $value === '' ? null : (int) $value, $data['limits'] ?? []), array_flip(FeatureCatalog::LIMITS));
        $data['modules'] = array_values($data['modules'] ?? []);
        $data['features'] = array_values($data['features'] ?? []);
        $data['mobile'] = array_values($data['mobile'] ?? []);

        if ($kind === 'addon') {
            return array_intersect_key($data, array_flip(['code', 'name_ar', 'name_en', 'price_monthly', 'modules', 'features', 'mobile', 'limits', 'is_active']));
        }

        return [...$data, 'price_yearly' => $data['price_yearly'] ?? 0, 'trial_days' => $data['trial_days'] ?? 0, 'sort_order' => $data['sort_order'] ?? 0];
    }
}
