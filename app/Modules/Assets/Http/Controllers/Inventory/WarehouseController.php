<?php

namespace App\Modules\Assets\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Modules\Assets\Actions\Inventory\ReceiveStock;
use App\Modules\Assets\Enums\AssetsFeature;
use App\Modules\Assets\Enums\HolderType;
use App\Modules\Assets\Enums\WarehouseKind;
use App\Modules\Assets\Http\Controllers\Inventory\Concerns\ScopesInventory;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\Stock;
use App\Modules\Assets\Models\StockMove;
use App\Modules\Assets\Models\Warehouse;
use App\Modules\Hr\Ports\StaffDirectory;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

/**
 * Warehouses and what is in them; plus what each employee is holding from handovers.
 */
class WarehouseController extends Controller
{
    use RendersSuitePages, ScopesInventory;

    public function index(): Response
    {
        $branches = $this->branchesFor(AssetsFeature::Inventory, FeatureAction::View);
        $names = $branches->mapWithKeys(fn (BranchRef $branch): array => [$branch->id => $branch->name]);
        $warehouses = $this->warehousesFor(AssetsFeature::Inventory);
        $stock = Stock::query()->with('variant.item')->where('holder_type', HolderType::Warehouse->value)->whereIn('holder_id', $warehouses->pluck('id'))->where('quantity', '>', 0)->get()->groupBy('holder_id');
        $people = collect($this->people());
        $held = Stock::query()->with('variant.item.unit')->where('holder_type', HolderType::Employee->value)->whereIn('holder_id', $people->pluck('value'))->where('quantity', '>', 0)->get();

        return $this->suitePage('Assets/Inventory/Warehouses', [
            'warehouses' => $warehouses->map(fn (Warehouse $warehouse): array => [
                ...$warehouse->only(['id', 'code', 'name_ar', 'name_en', 'branch_id', 'keeper_employee_id', 'is_active']), 'name' => $warehouse->localized('name'), 'kind' => $warehouse->kind->value,
                'branch' => $names[$warehouse->branch_id] ?? null, 'keeper' => $warehouse->keeper_employee_id ? $this->holderName('employee', $warehouse->keeper_employee_id) : null,
                'lines' => ($stock[$warehouse->id] ?? collect())->count(),
                'value' => (string) ($stock[$warehouse->id] ?? collect())->reduce(fn (BigDecimal $sum, Stock $row) => $sum->plus(BigDecimal::of((string) $row->quantity)->multipliedBy((string) $row->variant->unit_cost)), BigDecimal::zero())->toScale(2, RoundingMode::HalfUp),
                'low' => ($stock[$warehouse->id] ?? collect())->filter(fn (Stock $row): bool => $row->variant->item->min_stock !== null && (float) $row->quantity < (float) $row->variant->item->min_stock)->count(),
            ])->all(),
            'holdings' => $held->groupBy('holder_id')->map(fn ($rows, $employeeId): array => [
                'employee_id' => (int) $employeeId, 'employee' => $people->firstWhere('value', (int) $employeeId)['label'] ?? '—',
                'lines' => $rows->map(fn (Stock $row): array => ['label' => $row->variant->label(), 'quantity' => (string) (float) $row->quantity, 'unit' => $row->variant->item->unit->symbol])->values()->all(),
            ])->values()->all(),
            'branches' => $this->branchesFor(AssetsFeature::Inventory, FeatureAction::Create)->map(fn (BranchRef $branch): array => ['value' => $branch->id, 'label' => $branch->name, 'company_id' => $branch->companyId])->all(),
            'people' => $people->all(),
            'kinds' => WarehouseKind::options(),
        ], ['assets']);
    }

    public function show(Warehouse $warehouse): Response
    {
        $this->authorizeBranchFor(AssetsFeature::Inventory, FeatureAction::View, $warehouse->branch_id);
        $holder = ['holder_type' => HolderType::Warehouse->value, 'holder_id' => $warehouse->id];

        return $this->suitePage('Assets/Inventory/Warehouse', [
            'warehouse' => [...$warehouse->only(['id', 'code', 'branch_id']), 'name' => $warehouse->localized('name'), 'kind' => $warehouse->kind->value, 'keeper' => $warehouse->keeper_employee_id ? $this->holderName('employee', $warehouse->keeper_employee_id) : null],
            'stock' => Stock::query()->where($holder)->where('quantity', '>', 0)->with('variant.item.unit')->get()->sortBy(fn (Stock $row) => $row->variant->label())->values()->map(fn (Stock $row): array => [
                'variant_id' => $row->variant_id, 'label' => $row->variant->label(), 'sku' => $row->variant->sku, 'quantity' => (string) (float) $row->quantity, 'unit' => $row->variant->item->unit->symbol,
                'value' => (string) BigDecimal::of((string) $row->quantity)->multipliedBy((string) $row->variant->unit_cost)->toScale(2, RoundingMode::HalfUp),
                'low' => $row->variant->item->min_stock !== null && (float) $row->quantity < (float) $row->variant->item->min_stock,
            ])->all(),
            'assets' => Asset::query()->where('warehouse_id', $warehouse->id)->whereNotIn('status', ['disposed', 'lost'])->orderBy('tag')->get()->map(fn (Asset $asset): array => ['id' => $asset->id, 'tag' => $asset->tag, 'name' => $asset->localized('name'), 'status' => $asset->status->value])->all(),
            'moves' => StockMove::query()->where(fn ($query) => $query->where(['from_type' => 'warehouse', 'from_id' => $warehouse->id])->orWhere(['to_type' => 'warehouse', 'to_id' => $warehouse->id]))
                ->with('variant.item.unit')->latest('moved_at')->limit(50)->get()->map(fn (StockMove $move): array => [
                    'id' => $move->id, 'at' => $move->moved_at->toIso8601String(), 'label' => $move->variant->label(), 'reason' => $move->reason,
                    'quantity' => ($move->to_type === 'warehouse' && $move->to_id === $warehouse->id ? '+' : '−').(float) $move->quantity.' '.$move->variant->item->unit->symbol,
                    'document' => $move->document_type ? $move->document_type.':'.$move->document_id : null,
                ])->all(),
            'catalog' => $this->catalog(),
        ], ['assets']);
    }

    public function store(Request $request, CurrentTenant $tenant): RedirectResponse
    {
        $data = $this->validated($request);
        $branch = $this->branchesFor(AssetsFeature::Inventory, FeatureAction::Create)->first(fn (BranchRef $branch): bool => $branch->id === (int) $data['branch_id']) ?? abort(403);

        (new Warehouse(collect($data)->except(['branch_id', 'code'])->all()))->forceFill(['client_id' => $tenant->client()->id, 'company_id' => $branch->companyId, 'branch_id' => $branch->id, 'code' => strtoupper($data['code'])])->save();
        $this->toast(__('assets.inventory.saved'));

        return back();
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $this->authorizeBranchFor(AssetsFeature::Inventory, FeatureAction::Update, $warehouse->branch_id);
        $warehouse->fill(collect($this->validated($request, $warehouse))->except(['branch_id', 'code'])->all())->save();
        $this->toast(__('assets.inventory.saved'));

        return back();
    }

    public function receive(Request $request, Warehouse $warehouse, ReceiveStock $receive): RedirectResponse
    {
        $this->authorizeBranchFor(AssetsFeature::Inventory, FeatureAction::Create, $warehouse->branch_id);
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1', 'max:200'], 'lines.*.variant_id' => ['required', 'integer'], 'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit' => ['nullable', 'string', 'max:20'], 'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $receive->handle($warehouse, $data['lines'], $request->user());
        $this->toast(__('assets.inventory.received'));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Warehouse $warehouse = null): array
    {
        $data = $request->validate([
            'branch_id' => [$warehouse ? 'nullable' : 'required', 'integer'],
            'code' => [$warehouse ? 'nullable' : 'required', 'alpha_dash:ascii', 'max:20', Rule::unique(Warehouse::class)->where('client_id', app(CurrentTenant::class)->id())->ignore($warehouse?->id)],
            'name_ar' => ['required', 'string', 'max:255'], 'name_en' => ['required', 'string', 'max:255'], 'kind' => ['required', Rule::enum(WarehouseKind::class)],
            'keeper_employee_id' => ['nullable', 'integer'], 'is_active' => ['boolean'],
        ]);
        $companyId = $warehouse?->company_id ?? $this->branchesFor(AssetsFeature::Inventory, FeatureAction::Create)->firstWhere('id', (int) ($data['branch_id'] ?? 0))?->companyId;

        if (! empty($data['keeper_employee_id']) && app(StaffDirectory::class)->employee((int) $data['keeper_employee_id'])?->companyId !== $companyId) {
            throw ValidationException::withMessages(['keeper_employee_id' => __('assets.errors.employee')]);
        }

        return $data;
    }
}
