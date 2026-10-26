<?php

namespace App\Modules\Assets\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Modules\Assets\Actions\Inventory\Holders;
use App\Modules\Assets\Actions\Inventory\RecordIncident;
use App\Modules\Assets\Actions\Inventory\ResolveIncident;
use App\Modules\Assets\Actions\Inventory\StorePhotos;
use App\Modules\Assets\Enums\AssetsFeature;
use App\Modules\Assets\Enums\IncidentKind;
use App\Modules\Assets\Enums\IncidentResolution;
use App\Modules\Assets\Enums\IncidentStatus;
use App\Modules\Assets\Http\Controllers\Inventory\Concerns\ScopesInventory;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\Incident;
use App\Modules\Assets\Models\ItemVariant;
use App\Modules\Assets\Models\Warehouse;
use App\Modules\Assets\Support\Holder;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * What broke, went missing or was stolen, with photos, and how management settled it.
 */
class IncidentController extends Controller
{
    use RendersSuitePages, ScopesInventory;

    public function index(Request $request): Response
    {
        $filters = ['status' => $request->query('status', 'open'), 'kind' => $request->query('kind')];
        $companies = $this->companiesFor(AssetsFeature::Incidents, FeatureAction::View)->pluck('id');
        $query = Incident::query()->whereIn('company_id', $companies)
            ->when($filters['status'], fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['kind'], fn ($query, string $kind) => $query->where('kind', $kind));

        return $this->suitePage('Assets/Inventory/Incidents', [
            'incidents' => (clone $query)->with(['variant.item.unit', 'asset', 'attachments'])->latest('id')->paginate(30)->withQueryString()->through(fn (Incident $incident): array => self::summary($incident, withPhotos: true)),
            'totals' => ['count' => (clone $query)->count(), 'value' => (string) BigDecimal::of((string) ((clone $query)->sum('value') ?: 0))->toScale(2)],
            'by_kind' => (clone $query)->selectRaw('kind, count(*) as total, sum(value) as value')->groupBy('kind')->get()->map(fn ($row): array => ['kind' => $row->kind->value, 'count' => (int) $row->total, 'value' => (string) $row->value])->all(),
            'filters' => $filters,
            'kinds' => IncidentKind::options(),
            'resolutions' => IncidentResolution::options(),
            'statuses' => IncidentStatus::options(),
            'warehouses' => $this->warehousesFor(AssetsFeature::Incidents)->map(fn (Warehouse $warehouse): array => ['value' => $warehouse->id, 'label' => $warehouse->localized('name')])->values()->all(),
            'can' => ['resolve' => ! $this->scope()->branchesFor(AssetsFeature::Incidents->key('resolve'), $this->access())->isEmpty(), 'charge' => ! $this->scope()->branchesFor(AssetsFeature::Incidents->key('charge_employee'), $this->access())->isEmpty()],
        ], ['assets']);
    }

    public function store(Request $request, RecordIncident $record, StorePhotos $photos): RedirectResponse
    {
        $data = $request->validate([
            'holder' => ['required', 'string', 'regex:/^(warehouse|employee):\d+$/'], 'variant_id' => ['nullable', 'integer', 'required_without:asset_id'], 'asset_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'numeric', 'gt:0', 'required_with:variant_id'], 'kind' => ['required', Rule::enum(IncidentKind::class)], 'description' => ['nullable', 'string', 'max:500'],
            ...TransferController::photoRules(),
        ]);
        [$type, $id] = explode(':', $data['holder']);
        $holder = Holder::of($type, (int) $id);
        $place = app(Holders::class)->describe($holder);
        $this->authorizeBranchFor(AssetsFeature::Incidents, FeatureAction::Create, $place['branch_id']);

        DB::transaction(function () use ($data, $holder, $place, $record, $photos, $request): void {
            $incident = $record->handle(
                $data, ! empty($data['variant_id']) ? ItemVariant::query()->whereHas('item')->findOrFail($data['variant_id']) : null,
                ! empty($data['asset_id']) ? Asset::query()->where('company_id', $place['company_id'])->findOrFail($data['asset_id']) : null,
                $holder, 'report', null, $place['company_id'], $place['employee_id'], takeFromHolder: true, actor: $request->user(),
            );
            $photos->handle($request->file('photos', []), 'incident', $incident->id, 'incident', $request->user());
        });
        $this->toast(__('assets.incidents.reported'));

        return back();
    }

    public function resolve(Request $request, Incident $incident, ResolveIncident $resolve): RedirectResponse
    {
        $this->authorizeCompanyFor(AssetsFeature::Incidents, 'resolve', $incident->company_id);
        $data = $request->validate([
            'resolution' => ['required', Rule::enum(IncidentResolution::class)], 'note' => ['nullable', 'string', 'max:255'],
            'charge_amount' => ['nullable', 'numeric', 'gt:0'], 'warehouse_id' => ['nullable', 'integer'],
        ]);
        $resolution = IncidentResolution::from($data['resolution']);

        if ($resolution === IncidentResolution::Charged) {
            $this->authorizeCompanyFor(AssetsFeature::Incidents, 'charge_employee', $incident->company_id);
        }

        $warehouse = ! empty($data['warehouse_id']) ? $this->warehousesFor(AssetsFeature::Incidents)->firstWhere('id', (int) $data['warehouse_id']) ?? abort(403) : null;
        $resolve->handle($incident, $resolution, $data['note'] ?? null, isset($data['charge_amount']) ? (string) $data['charge_amount'] : null, $warehouse, $request->user());
        $this->toast(__('assets.incidents.resolved'));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(Incident $incident, bool $withPhotos = false): array
    {
        $holders = app(Holders::class);

        return [
            'id' => $incident->id, 'number' => $incident->number, 'kind' => $incident->kind->value, 'status' => $incident->status->value, 'label' => $incident->label(),
            'quantity' => (string) (float) $incident->quantity, 'unit' => $incident->variant?->item->unit?->symbol, 'value' => (string) $incident->value,
            'source' => $incident->source_type.($incident->source_id ? ':'.$incident->source_id : ''), 'description' => $incident->description,
            'holder' => $incident->holder_type && in_array($incident->holder_type->value, ['warehouse', 'employee'], true) ? $holders->name($incident->holder_type->value, $incident->holder_id) : null,
            'responsible' => $incident->responsible_employee_id ? $holders->name('employee', $incident->responsible_employee_id) : null, 'responsible_employee_id' => $incident->responsible_employee_id,
            'resolution' => $incident->resolution?->value, 'resolution_note' => $incident->resolution_note, 'charged_amount' => $incident->charged_amount !== null ? (string) $incident->charged_amount : null,
            'created_at' => $incident->created_at->toIso8601String(), 'resolved_at' => $incident->resolved_at?->toIso8601String(), 'is_asset' => $incident->asset_id !== null,
            'photos' => $withPhotos ? $incident->attachments->map(fn ($photo): array => ['id' => $photo->id, 'url' => $photo->temporaryUrl()])->all() : [],
        ];
    }
}
