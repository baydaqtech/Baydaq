<?php

namespace App\Modules\Assets\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Modules\Assets\Actions\Inventory\DefaultUnits;
use App\Modules\Assets\Actions\Inventory\SaveItem;
use App\Modules\Assets\Enums\AssetsFeature;
use App\Modules\Assets\Enums\UnitDimension;
use App\Modules\Assets\Http\Controllers\Inventory\Concerns\ScopesInventory;
use App\Modules\Assets\Models\Category;
use App\Modules\Assets\Models\Item;
use App\Modules\Assets\Models\Stock;
use App\Modules\Assets\Models\Unit;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * Stock items with their variants and packs, and the units they are counted in. Owner-wide.
 */
class CatalogController extends Controller
{
    use RendersSuitePages, ScopesInventory;

    public function index(DefaultUnits $defaults): Response
    {
        $defaults->ensure();
        $onHand = Stock::query()->selectRaw('variant_id, sum(quantity) as total')->groupBy('variant_id')->pluck('total', 'variant_id');

        return $this->suitePage('Assets/Inventory/Items', [
            'items' => Item::query()->with(['unit', 'variants.contentUnit', 'packs'])->orderBy('name_en')->get()->map(fn (Item $item): array => [
                ...$item->only(['id', 'category_id', 'unit_id', 'name_ar', 'name_en', 'description', 'is_active']), 'name' => $item->localized('name'),
                'min_stock' => $item->min_stock !== null ? (string) $item->min_stock : null, 'unit' => $item->unit->symbol,
                'variants' => $item->variants->map(fn ($variant): array => [
                    ...$variant->only(['id', 'name_ar', 'name_en', 'sku', 'barcode', 'content_unit_id', 'is_active']), 'name' => $variant->localized('name'),
                    'content' => $variant->content !== null ? (string) $variant->content : null, 'content_unit' => $variant->contentUnit?->symbol,
                    'unit_cost' => (string) $variant->unit_cost, 'on_hand' => (string) ($onHand[$variant->id] ?? '0'),
                ])->all(),
                'packs' => $item->packs->map(fn ($pack): array => [...$pack->only(['id', 'name_ar', 'name_en']), 'factor' => (string) $pack->factor])->all(),
            ])->all(),
            'units' => Unit::query()->orderBy('dimension')->orderBy('factor')->get()->map(fn (Unit $unit): array => [
                ...$unit->only(['id', 'name_ar', 'name_en', 'symbol', 'is_active']), 'name' => $unit->localized('name'), 'dimension' => $unit->dimension->value, 'factor' => (string) (float) $unit->factor,
            ])->all(),
            'categories' => Category::query()->where('is_active', true)->orderBy('name_en')->get()->map(fn (Category $category): array => ['value' => $category->id, 'label' => $category->localized('name')])->all(),
            'dimensions' => UnitDimension::options(),
        ], ['assets']);
    }

    public function storeItem(Request $request, SaveItem $save): RedirectResponse
    {
        return $this->saveItem($request, new Item, $save);
    }

    public function updateItem(Request $request, Item $item, SaveItem $save): RedirectResponse
    {
        return $this->saveItem($request, $item, $save);
    }

    public function storeUnit(Request $request, CurrentTenant $tenant): RedirectResponse
    {
        (new Unit($this->validatedUnit($request)))->forceFill(['client_id' => $tenant->client()->id])->save();
        $this->toast(__('assets.inventory.saved'));

        return back();
    }

    public function updateUnit(Request $request, Unit $unit): RedirectResponse
    {
        $data = $this->validatedUnit($request, $unit);

        // Units already counting stock keep their size; renaming is fine.
        if (Item::query()->where('unit_id', $unit->id)->exists()) {
            $data = collect($data)->except(['dimension', 'factor'])->all();
        }

        $unit->fill($data)->save();
        $this->toast(__('assets.inventory.saved'));

        return back();
    }

    private function saveItem(Request $request, Item $item, SaveItem $save): RedirectResponse
    {
        $client = app(CurrentTenant::class)->id();
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:255'], 'name_en' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'],
            'unit_id' => ['required', 'integer', Rule::exists(Unit::class, 'id')->where('client_id', $client)],
            'category_id' => ['nullable', 'integer', Rule::exists(Category::class, 'id')->where('client_id', $client)],
            'min_stock' => ['nullable', 'numeric', 'min:0'], 'is_active' => ['boolean'],
            'variants' => ['required', 'array', 'min:1', 'max:100'], 'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name_ar' => ['required', 'string', 'max:255'], 'variants.*.name_en' => ['required', 'string', 'max:255'],
            'variants.*.sku' => ['nullable', 'string', 'max:40'], 'variants.*.barcode' => ['nullable', 'string', 'max:64'],
            'variants.*.content' => ['nullable', 'numeric', 'min:0'], 'variants.*.content_unit_id' => ['nullable', 'integer', Rule::exists(Unit::class, 'id')->where('client_id', $client)],
            'variants.*.unit_cost' => ['nullable', 'numeric', 'min:0'], 'variants.*.is_active' => ['boolean'],
            'packs' => ['nullable', 'array', 'max:10'], 'packs.*.name_ar' => ['required', 'string', 'max:60'], 'packs.*.name_en' => ['required', 'string', 'max:60'], 'packs.*.factor' => ['required', 'numeric', 'gt:0'],
        ]);
        $this->authorizeAccount(AssetsFeature::Inventory->key($item->exists ? FeatureAction::Update : FeatureAction::Create));

        $variants = array_map(fn (array $variant): array => [...$variant, 'unit_cost' => $variant['unit_cost'] ?? 0], $data['variants']);
        $save->handle($item, collect($data)->except(['variants', 'packs'])->all(), $variants, $data['packs'] ?? []);
        $this->toast(__('assets.inventory.saved'));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedUnit(Request $request, ?Unit $unit = null): array
    {
        $this->authorizeAccount(AssetsFeature::Inventory->key($unit ? FeatureAction::Update : FeatureAction::Create));

        return $request->validate([
            'name_ar' => ['required', 'string', 'max:60'], 'name_en' => ['required', 'string', 'max:60'],
            'symbol' => ['required', 'string', 'max:12', Rule::unique(Unit::class)->where('client_id', app(CurrentTenant::class)->id())->ignore($unit?->id)],
            'dimension' => ['required', Rule::enum(UnitDimension::class)], 'factor' => ['required', 'numeric', 'gt:0'], 'is_active' => ['boolean'],
        ]);
    }
}
