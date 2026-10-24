<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Data\BrandRef;
use App\Modules\Platform\Data\CompanyRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\ReachesBranches;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Pos\Actions\SaveProduct;
use App\Modules\Pos\Enums\PosFeature;
use App\Modules\Pos\Models\Category;
use App\Modules\Pos\Models\ModifierGroup;
use App\Modules\Pos\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

class CatalogController extends Controller
{
    use ReachesBranches, RendersSuitePages;

    public function index(OrgDirectory $directory): Response
    {
        $companies = $this->companiesFor(PosFeature::Catalog, FeatureAction::View);
        $companyIds = $companies->pluck('id');

        return $this->suitePage('Pos/Catalog/Index', [
            'companies' => $companies->map(fn (CompanyRef $company): array => ['value' => $company->id, 'label' => $company->name])->all(),
            'brands' => collect($directory->brands())->filter(fn (BrandRef $brand) => $companyIds->contains($brand->companyId))->map(fn (BrandRef $brand): array => ['value' => $brand->id, 'label' => $brand->name, 'company_id' => $brand->companyId])->values()->all(),
            'branches' => collect($directory->branches())->filter(fn (BranchRef $branch) => $companyIds->contains($branch->companyId))->map(fn (BranchRef $branch): array => ['value' => $branch->id, 'label' => $branch->name, 'company_id' => $branch->companyId])->values()->all(),
            'categories' => Category::query()->whereIn('company_id', $companyIds)->orderBy('sort_order')->get()->map(fn (Category $category): array => [...$category->attributesToArray(), 'name' => $category->localized('name')])->all(),
            'groups' => ModifierGroup::query()->whereIn('company_id', $companyIds)->with('modifiers')->get()->map(fn (ModifierGroup $group): array => [...$group->attributesToArray(), 'name' => $group->localized('name'), 'modifiers' => $group->modifiers->map(fn ($modifier) => [...$modifier->attributesToArray(), 'name' => $modifier->localized('name')])->all()])->all(),
            'products' => Product::query()->whereIn('company_id', $companyIds)->with(['category', 'modifierGroups'])->orderBy('sort_order')->orderBy('name_en')->get()->map(fn (Product $product): array => [
                ...$product->attributesToArray(), 'name' => $product->localized('name'), 'category' => $product->category?->localized('name'),
                'modifier_group_ids' => $product->modifierGroups->pluck('id')->all(),
                'branch_overrides' => $product->getConnection()->table('pos__branch_products')->where('product_id', $product->id)->get()->map(fn ($row) => ['branch_id' => (int) $row->branch_id, 'price' => $row->price, 'is_available' => (bool) $row->is_available])->all(),
            ])->all(),
        ], ['pos']);
    }

    public function storeCategory(Request $request, CurrentTenant $tenant): RedirectResponse
    {
        $this->save($request, new Category, $tenant);
        $this->toast(__('pos.catalog.saved'));

        return back();
    }

    public function updateCategory(Request $request, Category $category, CurrentTenant $tenant): RedirectResponse
    {
        $this->save($request, $category, $tenant);
        $this->toast(__('pos.catalog.saved'));

        return back();
    }

    public function storeProduct(Request $request, SaveProduct $save): RedirectResponse
    {
        $this->saveProduct($request, new Product, $save);
        $this->toast(__('pos.catalog.saved'));

        return back();
    }

    public function updateProduct(Request $request, Product $product, SaveProduct $save): RedirectResponse
    {
        $this->saveProduct($request, $product, $save);
        $this->toast(__('pos.catalog.saved'));

        return back();
    }

    public function storeGroup(Request $request, CurrentTenant $tenant): RedirectResponse
    {
        $this->saveGroup($request, new ModifierGroup, $tenant);
        $this->toast(__('pos.catalog.saved'));

        return back();
    }

    public function updateGroup(Request $request, ModifierGroup $group, CurrentTenant $tenant): RedirectResponse
    {
        $this->saveGroup($request, $group, $tenant);
        $this->toast(__('pos.catalog.saved'));

        return back();
    }

    private function save(Request $request, Category $category, CurrentTenant $tenant): void
    {
        $data = $request->validate(['company_id' => ['required', 'integer'], 'brand_id' => ['nullable', 'integer'], 'name_ar' => ['required', 'string', 'max:255'], 'name_en' => ['required', 'string', 'max:255'], 'color' => ['nullable', 'string', 'max:7'], 'sort_order' => ['nullable', 'integer'], 'is_active' => ['boolean']]);
        $this->authorizeCatalogCompany($data['company_id']);

        if (! $category->exists) {
            $category->client_id = $tenant->client()->id;
        }

        $category->fill($data)->save();
    }

    private function saveProduct(Request $request, Product $product, SaveProduct $save): void
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer'], 'category_id' => ['nullable', 'integer'], 'sku' => ['nullable', 'string', 'max:40'],
            'name_ar' => ['required', 'string', 'max:255'], 'name_en' => ['required', 'string', 'max:255'], 'description_ar' => ['nullable', 'string'], 'description_en' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'], 'vat_rate' => ['required', Rule::in(['0', '0.00', '5', '5.00', '15', '15.00'])],
            'calories' => ['nullable', 'integer', 'min:0', 'max:9999'], 'high_salt' => ['boolean'], 'caffeine_mg' => ['nullable', 'integer', 'min:0', 'max:2000'], 'allergens' => ['nullable', 'array'], 'allergens.*' => ['string', 'max:40'],
            'is_active' => ['boolean'], 'sold_by_weight' => ['boolean'], 'sort_order' => ['nullable', 'integer'],
            'modifier_group_ids' => ['nullable', 'array'], 'modifier_group_ids.*' => ['integer'],
            'branch_overrides' => ['nullable', 'array'], 'branch_overrides.*.branch_id' => ['required', 'integer'], 'branch_overrides.*.price' => ['nullable', 'numeric', 'min:0'], 'branch_overrides.*.is_available' => ['boolean'],
        ]);
        $this->authorizeCatalogCompany($data['company_id']);

        $save->handle($product, collect($data)->except(['modifier_group_ids', 'branch_overrides'])->all(), $data['modifier_group_ids'] ?? [], $data['branch_overrides'] ?? []);
    }

    private function saveGroup(Request $request, ModifierGroup $group, CurrentTenant $tenant): void
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer'], 'name_ar' => ['required', 'string', 'max:255'], 'name_en' => ['required', 'string', 'max:255'], 'min' => ['required', 'integer', 'min:0', 'max:20'], 'max' => ['required', 'integer', 'min:1', 'max:20'],
            'modifiers' => ['required', 'array', 'min:1'], 'modifiers.*.name_ar' => ['required', 'string', 'max:255'], 'modifiers.*.name_en' => ['required', 'string', 'max:255'], 'modifiers.*.price' => ['required', 'numeric', 'min:0'],
        ]);
        $this->authorizeCatalogCompany($data['company_id']);

        if (! $group->exists) {
            $group->client_id = $tenant->client()->id;
        }

        $group->fill(collect($data)->except('modifiers')->all())->save();
        $group->modifiers()->delete();
        $group->modifiers()->createMany(array_map(fn (array $modifier, int $index): array => [...$modifier, 'sort_order' => $index], $data['modifiers'], array_keys($data['modifiers'])));
    }

    private function authorizeCatalogCompany(int $companyId): void
    {
        abort_unless($this->companiesFor(PosFeature::Catalog, FeatureAction::Create)->contains(fn (CompanyRef $company): bool => $company->id === $companyId), 403);
    }
}
