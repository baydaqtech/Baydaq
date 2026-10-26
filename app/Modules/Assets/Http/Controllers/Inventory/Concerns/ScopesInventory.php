<?php

namespace App\Modules\Assets\Http\Controllers\Inventory\Concerns;

use App\Modules\Assets\Actions\Inventory\Holders;
use App\Modules\Assets\Enums\AssetsFeature;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\ItemVariant;
use App\Modules\Assets\Models\Stock;
use App\Modules\Assets\Models\Warehouse;
use App\Modules\Hr\Ports\StaffDirectory;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\ReachesBranches;
use Illuminate\Support\Collection;

/**
 * What inventory pages share: warehouses the user reaches, the variants catalog for scanning,
 * the assets and employees that can be handed over, and holder names.
 */
trait ScopesInventory
{
    use ReachesBranches;

    /** @return Collection<int, Warehouse> */
    protected function warehousesFor(AssetsFeature $feature, FeatureAction|string $action = FeatureAction::View): Collection
    {
        return Warehouse::query()->whereIn('branch_id', $this->branchesFor($feature, $action)->pluck('id'))->orderBy('code')->get();
    }

    /** @return list<array<string, mixed>> every active variant with what a scanner or a person can type */
    protected function catalog(): array
    {
        return ItemVariant::query()->where('is_active', true)->whereHas('item', fn ($query) => $query->where('is_active', true))->with(['item.unit', 'item.packs'])->get()
            ->map(fn (ItemVariant $variant): array => [
                'id' => $variant->id, 'label' => $variant->label(), 'sku' => $variant->sku, 'barcode' => $variant->barcode, 'unit' => $variant->item->unit->symbol,
                'unit_cost' => (string) $variant->unit_cost,
                'units' => [['value' => '', 'label' => $variant->item->unit->symbol], ...$variant->item->packs->map(fn ($pack): array => ['value' => 'pack:'.$pack->id, 'label' => $pack->localized('name').' ('.(float) $pack->factor.' '.$variant->item->unit->symbol.')'])->all()],
            ])->sortBy('label')->values()->all();
    }

    /**
     * Stock on hand for the given holders: "warehouse:3" / "employee:12" → variant id → quantity.
     *
     * @param  list<string>  $holders
     * @return array<string, array<int, string>>
     */
    protected function stockOf(array $holders): array
    {
        $rows = Stock::query()->where('quantity', '>', 0)->where(function ($query) use ($holders): void {
            foreach ($holders as $holder) {
                [$type, $id] = explode(':', $holder);
                $query->orWhere(fn ($query) => $query->where('holder_type', $type)->where('holder_id', (int) $id));
            }
        })->get();

        return $rows->groupBy(fn (Stock $row): string => $row->holder_type->value.':'.$row->holder_id)
            ->map(fn (Collection $group): array => $group->mapWithKeys(fn (Stock $row): array => [$row->variant_id => (string) $row->quantity])->all())->all();
    }

    /** @return list<array<string, mixed>> tagged assets that can move, with who or where holds them */
    protected function movableAssets(Collection $companyIds): array
    {
        return Asset::query()->whereIn('company_id', $companyIds)->whereNotIn('status', ['disposed', 'lost'])->with('openCustody')->orderBy('tag')->get()
            ->map(fn (Asset $asset): array => [
                'id' => $asset->id, 'tag' => $asset->tag, 'label' => $asset->tag.' '.$asset->localized('name'), 'serial' => $asset->serial, 'status' => $asset->status->value,
                'holder' => $asset->openCustody ? 'employee:'.$asset->openCustody->employee_id : ($asset->warehouse_id ? 'warehouse:'.$asset->warehouse_id : null),
            ])->all();
    }

    /** @return list<array{value: int, label: string, company_id: int, branch_id: int}> */
    protected function people(): array
    {
        $staff = app(StaffDirectory::class);

        return $this->branchesFor(AssetsFeature::Handovers, FeatureAction::View)
            ->flatMap(fn (BranchRef $branch) => $staff->staffAt($branch->id))->unique('id')
            ->map(fn ($employee): array => ['value' => $employee->id, 'label' => $employee->name, 'company_id' => $employee->companyId, 'branch_id' => $employee->branchId])->values()->all();
    }

    protected function holderName(string $type, int $id): string
    {
        return app(Holders::class)->name($type, $id);
    }
}
