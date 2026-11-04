<?php

namespace App\Modules\Assets\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Assets\Actions\Inventory\Holders;
use App\Modules\Assets\Actions\Inventory\ManageHandover;
use App\Modules\Assets\Actions\Inventory\RecordIncident;
use App\Modules\Assets\Actions\Inventory\StorePhotos;
use App\Modules\Assets\Enums\HandoverStatus;
use App\Modules\Assets\Enums\HolderType;
use App\Modules\Assets\Enums\IncidentKind;
use App\Modules\Assets\Http\Controllers\Inventory\HandoverController;
use App\Modules\Assets\Http\Controllers\Inventory\TransferController;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\Handover;
use App\Modules\Assets\Models\ItemVariant;
use App\Modules\Assets\Models\Stock;
use App\Modules\Assets\Models\Warehouse;
use App\Modules\Assets\Support\Holder;
use App\Modules\Hr\Data\EmployeeSummary;
use App\Modules\Hr\Ports\StaffDirectory;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Ports\Entitlements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The employee app's inventory: what I hold, scanning, handing over and receiving (with photos and
 * location), and reporting what broke or went missing. Keepers also act for their warehouses.
 * Enum values travel as values; the app translates them.
 */
class InventoryController extends Controller
{
    public function __construct(private StaffDirectory $staff, private Holders $holders) {}

    public function holdings(Request $request): JsonResponse
    {
        $me = $this->me($request);

        return response()->json([
            'employee' => ['id' => $me->id, 'name' => $me->name],
            'stock' => Stock::query()->where('holder_type', HolderType::Employee->value)->where('holder_id', $me->id)->where('quantity', '>', 0)->with('variant.item.unit')->get()
                ->map(fn (Stock $row): array => ['variant_id' => $row->variant_id, 'label' => $row->variant->label(), 'quantity' => (string) (float) $row->quantity, 'unit' => $row->variant->item->unit->symbol])->values()->all(),
            'assets' => Asset::query()->whereHas('openCustody', fn ($query) => $query->where('employee_id', $me->id))->get()
                ->map(fn (Asset $asset): array => ['id' => $asset->id, 'tag' => $asset->tag, 'name' => $asset->localized('name'), 'status' => $asset->status->value])->all(),
            'warehouses' => $this->keptWarehouses($me)->map(fn (Warehouse $warehouse): array => ['id' => $warehouse->id, 'name' => $warehouse->localized('name')])->values()->all(),
        ]);
    }

    /** Scan: a barcode or SKU of a variant, or an asset tag, within my company. */
    public function lookup(Request $request): JsonResponse
    {
        $me = $this->me($request);
        $code = trim((string) $request->validate(['code' => ['required', 'string', 'max:64']])['code']);

        $asset = Asset::query()->where('company_id', $me->companyId)->where('tag', strtoupper($code))->first();
        if ($asset) {
            return response()->json(['type' => 'asset', 'id' => $asset->id, 'label' => $asset->tag.' '.$asset->localized('name'), 'status' => $asset->status->value]);
        }

        $variant = ItemVariant::query()->whereHas('item')->where('is_active', true)->where(fn ($query) => $query->where('barcode', $code)->orWhere('sku', $code))->with('item.unit', 'item.packs')->first();
        abort_if($variant === null, 404, __('assets.inventory.scan_unknown', ['code' => $code]));

        return response()->json([
            'type' => 'variant', 'id' => $variant->id, 'label' => $variant->label(), 'unit' => $variant->item->unit->symbol,
            'packs' => $variant->item->packs->map(fn ($pack): array => ['unit' => 'pack:'.$pack->id, 'name' => $pack->localized('name'), 'factor' => (string) (float) $pack->factor])->all(),
            'on_hand' => (string) (float) (Stock::query()->where(['holder_type' => 'employee', 'holder_id' => $me->id, 'variant_id' => $variant->id])->value('quantity') ?? 0),
        ]);
    }

    /** Who I can hand things to: my company's warehouses and my branch colleagues. */
    public function recipients(Request $request): JsonResponse
    {
        $me = $this->me($request);

        return response()->json([
            'warehouses' => Warehouse::query()->where('company_id', $me->companyId)->where('is_active', true)->orderBy('code')->get()->map(fn (Warehouse $warehouse): array => ['holder' => 'warehouse:'.$warehouse->id, 'name' => $warehouse->localized('name')])->all(),
            'people' => collect($this->staff->staffAt($me->branchId))->reject(fn (EmployeeSummary $person): bool => $person->id === $me->id)
                ->map(fn (EmployeeSummary $person): array => ['holder' => 'employee:'.$person->id, 'name' => $person->name])->values()->all(),
        ]);
    }

    public function handovers(Request $request): JsonResponse
    {
        $me = $this->me($request);
        $warehouses = $this->keptWarehouses($me)->pluck('id');

        $mine = Handover::query()->where(fn ($query) => $query
            ->where(fn ($query) => $query->where('to_type', 'employee')->where('to_id', $me->id))
            ->orWhere(fn ($query) => $query->where('from_type', 'employee')->where('from_id', $me->id))
            ->orWhere(fn ($query) => $query->where('to_type', 'warehouse')->whereIn('to_id', $warehouses))
            ->orWhere(fn ($query) => $query->where('from_type', 'warehouse')->whereIn('from_id', $warehouses)))
            ->withCount('lines')->orderByRaw("status = 'pending' desc")->latest('id')->limit(50)->get();

        return response()->json(['handovers' => $mine->map(fn (Handover $handover): array => [
            ...HandoverController::summary($handover), 'to_me' => $handover->status === HandoverStatus::Pending && $this->receives($handover, $me, $warehouses->all()),
        ])->all()]);
    }

    public function show(Request $request, Handover $handover): JsonResponse
    {
        $me = $this->me($request);
        $warehouses = $this->keptWarehouses($me)->pluck('id')->all();
        abort_unless($this->receives($handover, $me, $warehouses) || $this->sends($handover, $me, $warehouses), 403);

        return response()->json([
            'handover' => [...HandoverController::summary($handover), 'to_me' => $handover->status === HandoverStatus::Pending && $this->receives($handover, $me, $warehouses)],
            'lines' => TransferController::lineArrays($handover->lines()->with(['variant.item.unit', 'asset'])->get()),
            'photos' => $handover->attachments()->get()->map(fn ($photo): array => ['url' => $photo->temporaryUrl(), 'stage' => $photo->stage])->all(),
        ]);
    }

    public function store(Request $request, ManageHandover $handovers): JsonResponse
    {
        $me = $this->me($request);
        $data = $request->validate([
            'from' => ['nullable', 'string', 'regex:/^warehouse:\d+$/'], 'to' => ['required', 'string', 'regex:/^(warehouse|employee):\d+$/'],
            'purpose' => ['nullable', 'string', 'max:255'], 'note' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            ...TransferController::lineRules(),
            'write_offs' => ['nullable', 'array', 'max:50'], 'write_offs.*.variant_id' => ['nullable', 'integer'], 'write_offs.*.asset_id' => ['nullable', 'integer'],
            'write_offs.*.kind' => ['required', Rule::enum(IncidentKind::class)], 'write_offs.*.quantity' => ['nullable', 'numeric', 'gt:0'], 'write_offs.*.description' => ['nullable', 'string', 'max:500'],
            ...TransferController::photoRules(),
        ]);

        // From me, or from a warehouse I keep.
        $from = Holder::employee($me->id);
        if (! empty($data['from'])) {
            $warehouseId = (int) explode(':', $data['from'])[1];
            abort_unless($this->keptWarehouses($me)->contains('id', $warehouseId), 403);
            $from = Holder::warehouse($warehouseId);
        }

        [$type, $id] = explode(':', $data['to']);
        $handover = $handovers->create($from, Holder::of($type, (int) $id), $data['lines'], $data['write_offs'] ?? [], [...$data, 'channel' => 'mobile'], $request->file('photos', []), $request->user());

        return response()->json(['id' => $handover->id, 'number' => $handover->number, 'status' => $handover->status->value], 201);
    }

    public function accept(Request $request, Handover $handover, ManageHandover $handovers): JsonResponse
    {
        $me = $this->me($request);
        abort_unless($this->receives($handover, $me, $this->keptWarehouses($me)->pluck('id')->all()), 403);
        $data = $request->validate([...TransferController::countRules(), ...TransferController::photoRules()]);

        $handover = $handovers->accept($handover, $data['counts'] ?? [], $data['explanations'] ?? [], $request->file('photos', []), $request->user());

        return response()->json(['id' => $handover->id, 'status' => $handover->status->value, 'incidents' => $handover->incidents()->count()]);
    }

    public function reject(Request $request, Handover $handover, ManageHandover $handovers): JsonResponse
    {
        $me = $this->me($request);
        $warehouses = $this->keptWarehouses($me)->pluck('id')->all();
        $receiving = $this->receives($handover, $me, $warehouses);
        abort_unless($receiving || $this->sends($handover, $me, $warehouses), 403);

        $handover = $handovers->reject($handover, $request->validate(['reason' => ['nullable', 'string', 'max:255']])['reason'] ?? null, $receiving ? HandoverStatus::Rejected : HandoverStatus::Cancelled, $request->user());

        return response()->json(['id' => $handover->id, 'status' => $handover->status->value]);
    }

    /** Something I hold broke or went missing. */
    public function report(Request $request, RecordIncident $record, StorePhotos $photos): JsonResponse
    {
        $me = $this->me($request);
        $data = $request->validate([
            'variant_id' => ['nullable', 'integer', 'required_without:asset_id'], 'asset_id' => ['nullable', 'integer'], 'quantity' => ['nullable', 'numeric', 'gt:0', 'required_with:variant_id'],
            'kind' => ['required', Rule::enum(IncidentKind::class)], 'description' => ['nullable', 'string', 'max:500'], ...TransferController::photoRules(),
        ]);

        $asset = ! empty($data['asset_id']) ? Asset::query()->whereHas('openCustody', fn ($query) => $query->where('employee_id', $me->id))->findOrFail($data['asset_id']) : null;
        $incident = DB::transaction(function () use ($data, $asset, $me, $record, $photos, $request) {
            $incident = $record->handle($data, $asset ? null : ItemVariant::query()->whereHas('item')->findOrFail($data['variant_id']), $asset, Holder::employee($me->id), 'report', null, $me->companyId, $me->id, takeFromHolder: true, actor: $request->user());
            $photos->handle($request->file('photos', []), 'incident', $incident->id, 'incident', $request->user());

            return $incident;
        });

        return response()->json(['id' => $incident->id, 'number' => $incident->number, 'value' => (string) $incident->value], 201);
    }

    private function me(Request $request): EmployeeSummary
    {
        abort_unless(app(Entitlements::class)->isActive(Module::Assets), 403, __('suite.modules.inactive'));

        return $this->staff->employeeForUser($request->user()->id) ?? abort(403, __('hr.attendance.errors.not_employee'));
    }

    /** @return Collection<int, Warehouse> */
    private function keptWarehouses(EmployeeSummary $me)
    {
        return Warehouse::query()->where('keeper_employee_id', $me->id)->where('is_active', true)->get();
    }

    /** @param list<int> $warehouses */
    private function receives(Handover $handover, EmployeeSummary $me, array $warehouses): bool
    {
        return ($handover->to_type === HolderType::Employee && $handover->to_id === $me->id) || ($handover->to_type === HolderType::Warehouse && in_array($handover->to_id, $warehouses, true));
    }

    /** @param list<int> $warehouses */
    private function sends(Handover $handover, EmployeeSummary $me, array $warehouses): bool
    {
        return ($handover->from_type === HolderType::Employee && $handover->from_id === $me->id) || ($handover->from_type === HolderType::Warehouse && in_array($handover->from_id, $warehouses, true));
    }
}
