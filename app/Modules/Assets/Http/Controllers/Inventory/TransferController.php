<?php

namespace App\Modules\Assets\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Modules\Assets\Actions\Inventory\ManageTransfer;
use App\Modules\Assets\Enums\AssetsFeature;
use App\Modules\Assets\Enums\IncidentKind;
use App\Modules\Assets\Enums\TransferStatus;
use App\Modules\Assets\Http\Controllers\Inventory\Concerns\ScopesInventory;
use App\Modules\Assets\Models\MovementLine;
use App\Modules\Assets\Models\Transfer;
use App\Modules\Assets\Models\Warehouse;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Response;

class TransferController extends Controller
{
    use RendersSuitePages, ScopesInventory;

    public function index(Request $request): Response
    {
        $warehouses = $this->warehousesFor(AssetsFeature::Transfers);
        $ids = $warehouses->pluck('id');
        $names = $warehouses->mapWithKeys(fn (Warehouse $warehouse): array => [$warehouse->id => $warehouse->localized('name')]);
        $all = Warehouse::query()->where('is_active', true)->orderBy('code')->get();

        return $this->suitePage('Assets/Inventory/Transfers', [
            'transfers' => Transfer::query()->where(fn ($query) => $query->whereIn('from_warehouse_id', $ids)->orWhereIn('to_warehouse_id', $ids))
                ->when($request->query('status'), fn ($query, string $status) => $query->where('status', $status))
                ->withCount('lines')->latest('id')->paginate(30)->withQueryString()->through(fn (Transfer $transfer): array => [
                    'id' => $transfer->id, 'number' => $transfer->number, 'status' => $transfer->status->value, 'lines' => $transfer->lines_count,
                    'from' => $names[$transfer->from_warehouse_id] ?? $this->holderName('warehouse', $transfer->from_warehouse_id),
                    'to' => $names[$transfer->to_warehouse_id] ?? $this->holderName('warehouse', $transfer->to_warehouse_id),
                    'created_at' => $transfer->created_at->toIso8601String(), 'sent_at' => $transfer->sent_at?->toIso8601String(), 'received_at' => $transfer->received_at?->toIso8601String(),
                ]),
            'filters' => ['status' => $request->query('status')],
            'statuses' => TransferStatus::options(),
            'sources' => $this->warehousesFor(AssetsFeature::Transfers, FeatureAction::Create)->map(fn (Warehouse $warehouse): array => ['value' => $warehouse->id, 'label' => $warehouse->localized('name')])->values()->all(),
            'destinations' => $all->map(fn (Warehouse $warehouse): array => ['value' => $warehouse->id, 'label' => $warehouse->localized('name')])->values()->all(),
            'catalog' => $this->catalog(),
            'stock' => $this->stockOf($warehouses->map(fn (Warehouse $warehouse): string => 'warehouse:'.$warehouse->id)->all()),
            'assets' => $this->movableAssets($warehouses->pluck('company_id')->unique()),
        ], ['assets']);
    }

    public function store(Request $request, ManageTransfer $transfers): RedirectResponse
    {
        $data = $request->validate([
            'from_warehouse_id' => ['required', 'integer'], 'to_warehouse_id' => ['required', 'integer', 'different:from_warehouse_id'], 'note' => ['nullable', 'string', 'max:255'],
            ...self::lineRules(),
        ]);
        $from = Warehouse::query()->findOrFail($data['from_warehouse_id']);
        $this->authorizeBranchFor(AssetsFeature::Transfers, FeatureAction::Create, $from->branch_id);

        $transfer = $transfers->create($from, Warehouse::query()->findOrFail($data['to_warehouse_id']), $data['lines'], $data['note'] ?? null, $request->user());

        return to_route('assets.inventory.transfers.show', $transfer);
    }

    public function show(Transfer $transfer): Response
    {
        $from = Warehouse::query()->findOrFail($transfer->from_warehouse_id);
        $to = Warehouse::query()->findOrFail($transfer->to_warehouse_id);
        $reach = $this->warehousesFor(AssetsFeature::Transfers)->pluck('id');
        abort_unless($reach->contains($from->id) || $reach->contains($to->id), 403);

        return $this->suitePage('Assets/Inventory/Transfer', [
            'transfer' => [
                'id' => $transfer->id, 'number' => $transfer->number, 'status' => $transfer->status->value, 'note' => $transfer->note,
                'from' => $from->localized('name'), 'to' => $to->localized('name'),
                'sent_at' => $transfer->sent_at?->toIso8601String(), 'received_at' => $transfer->received_at?->toIso8601String(),
            ],
            'lines' => self::lineArrays($transfer->lines()->with(['variant.item.unit', 'asset'])->get()),
            'incidents' => $transfer->incidents()->with(['variant.item', 'asset'])->get()->map(fn ($incident): array => IncidentController::summary($incident))->all(),
            'photos' => $transfer->attachments()->get()->map(fn ($photo): array => ['id' => $photo->id, 'url' => $photo->temporaryUrl(), 'stage' => $photo->stage])->all(),
            'can' => [
                'send' => $transfer->status === TransferStatus::Draft && $this->access()->allowsBranch(AssetsFeature::Transfers->key('send'), $from->branch_id, $from->company_id),
                'receive' => $transfer->status === TransferStatus::InTransit && $this->access()->allowsBranch(AssetsFeature::Transfers->key('receive'), $to->branch_id, $to->company_id),
                'cancel' => in_array($transfer->status, [TransferStatus::Draft, TransferStatus::InTransit], true) && $this->access()->allowsBranch(AssetsFeature::Transfers->key(FeatureAction::Create), $from->branch_id, $from->company_id),
            ],
            'kinds' => IncidentKind::options(),
        ], ['assets']);
    }

    public function send(Request $request, Transfer $transfer, ManageTransfer $transfers): RedirectResponse
    {
        $from = Warehouse::query()->findOrFail($transfer->from_warehouse_id);
        abort_unless($this->access()->allowsBranch(AssetsFeature::Transfers->key('send'), $from->branch_id, $from->company_id), 403);
        $request->validate(self::photoRules());

        $transfers->send($transfer, $request->file('photos', []), $request->user());
        $this->toast(__('assets.transfers.sent'));

        return back();
    }

    public function receive(Request $request, Transfer $transfer, ManageTransfer $transfers): RedirectResponse
    {
        $to = Warehouse::query()->findOrFail($transfer->to_warehouse_id);
        abort_unless($this->access()->allowsBranch(AssetsFeature::Transfers->key('receive'), $to->branch_id, $to->company_id), 403);
        $data = $request->validate([...self::countRules(), ...self::photoRules()]);

        $transfers->receive($transfer, $data['counts'] ?? [], $data['explanations'] ?? [], $request->file('photos', []), $request->user());
        $this->toast(__('assets.transfers.received'));

        return back();
    }

    public function cancel(Request $request, Transfer $transfer, ManageTransfer $transfers): RedirectResponse
    {
        $from = Warehouse::query()->findOrFail($transfer->from_warehouse_id);
        abort_unless($this->access()->allowsBranch(AssetsFeature::Transfers->key(FeatureAction::Create), $from->branch_id, $from->company_id), 403);
        $transfers->cancel($transfer, $request->user());
        $this->toast(__('assets.transfers.cancelled'));

        return back();
    }

    /** @return array<string, list<mixed>> */
    public static function lineRules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:200'], 'lines.*.variant_id' => ['nullable', 'integer', 'required_without:lines.*.asset_id'],
            'lines.*.asset_id' => ['nullable', 'integer'], 'lines.*.quantity' => ['nullable', 'numeric', 'gt:0', 'required_with:lines.*.variant_id'],
            'lines.*.unit' => ['nullable', 'string', 'max:20'], 'lines.*.condition' => ['nullable', 'string', 'max:255'], 'lines.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, list<mixed>> */
    public static function countRules(): array
    {
        return [
            'counts' => ['nullable', 'array'], 'counts.*' => ['numeric', 'min:0'],
            'explanations' => ['nullable', 'array'], 'explanations.*' => ['array', 'max:10'],
            'explanations.*.*.kind' => ['required', Rule::enum(IncidentKind::class)], 'explanations.*.*.quantity' => ['required', 'numeric', 'gt:0'],
            'explanations.*.*.description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, list<mixed>> */
    public static function photoRules(): array
    {
        return ['photos' => ['nullable', 'array', 'max:10'], 'photos.*' => ['image', 'max:'.config('baydaq.assets.photo_max_kb')]];
    }

    /**
     * @param  Collection<int, MovementLine>  $lines
     * @return list<array<string, mixed>>
     */
    public static function lineArrays($lines): array
    {
        return $lines->map(fn (MovementLine $line): array => [
            'id' => $line->id, 'label' => $line->label(), 'is_asset' => $line->asset_id !== null, 'asset_id' => $line->asset_id, 'variant_id' => $line->variant_id,
            'quantity' => (string) (float) $line->quantity, 'received' => $line->received !== null ? (string) (float) $line->received : null,
            'unit' => $line->variant?->item->unit->symbol, 'entered' => $line->entered_unit, 'condition' => $line->condition, 'note' => $line->note,
        ])->values()->all();
    }
}
