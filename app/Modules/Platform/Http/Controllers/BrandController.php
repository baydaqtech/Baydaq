<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Actions\Suite\SaveBrand;
use App\Modules\Platform\Enums\SuiteFeature;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Brand;
use App\Modules\Platform\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class BrandController extends Controller
{
    use AuthorizesSuite, RendersSuitePages;

    public function index(): Response
    {
        $reach = $this->access()->branches(SuiteFeature::Structure->key('view'));
        $companies = Company::query()->get()->filter(fn (Company $company) => $reach->allowsWholeCompany($company->id));

        return $this->suitePage('Suite/Structure/Brands', [
            'brands' => Brand::query()->whereIn('company_id', $companies->pluck('id'))->with('branches:id')->orderBy('name_en')->get()->map(fn (Brand $brand): array => [
                'id' => $brand->id, 'company_id' => $brand->company_id, 'slug' => $brand->slug, 'name' => $brand->localized('name'), 'name_ar' => $brand->name_ar, 'name_en' => $brand->name_en,
                'color' => $brand->color, 'is_active' => $brand->is_active, 'branch_ids' => $brand->branches->pluck('id')->all(),
            ])->all(),
            'companies' => $companies->map(fn (Company $company): array => ['value' => $company->id, 'label' => $company->localized('name')])->values()->all(),
            'branches' => Branch::query()->whereIn('company_id', $companies->pluck('id'))->get()->map(fn (Branch $branch): array => ['value' => $branch->id, 'label' => $branch->localized('name'), 'company_id' => $branch->company_id])->all(),
        ]);
    }

    public function store(Request $request, SaveBrand $save): RedirectResponse
    {
        $data = $this->validated($request);
        $this->authorizeCompany(SuiteFeature::Structure->key('create'), Company::query()->findOrFail($data['company_id']));
        $save->handle(new Brand, collect($data)->except('branch_ids')->all(), $data['branch_ids']);
        $this->toast(__('suite.structure.saved'));

        return back();
    }

    public function update(Request $request, Brand $brand, SaveBrand $save): RedirectResponse
    {
        $data = $this->validated($request);
        $this->authorizeCompany(SuiteFeature::Structure->key('update'), $brand->company);
        $save->handle($brand, collect($data)->except(['branch_ids', 'company_id'])->all(), $data['branch_ids']);
        $this->toast(__('suite.structure.saved'));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'company_id' => ['required', 'integer'], 'name_ar' => ['required', 'string', 'max:255'], 'name_en' => ['required', 'string', 'max:255'], 'slug' => ['nullable', 'alpha_dash:ascii', 'max:60'],
            'color' => ['nullable', 'string', 'max:7'], 'is_active' => ['boolean'], 'branch_ids' => ['required', 'array', 'min:1'], 'branch_ids.*' => ['integer'],
        ]);
    }
}
