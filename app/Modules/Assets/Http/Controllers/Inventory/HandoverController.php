<?php

namespace App\Modules\Assets\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Modules\Assets\Actions\Inventory\Holders;
use App\Modules\Assets\Actions\Inventory\ManageHandover;
use App\Modules\Assets\Enums\AssetsFeature;
use App\Modules\Assets\Enums\HandoverStatus;
use App\Modules\Assets\Enums\HolderType;
use App\Modules\Assets\Enums\IncidentKind;
use App\Modules\Assets\Http\Controllers\Inventory\Concerns\ScopesInventory;
use App\Modules\Assets\Models\Handover;
use App\Modules\Assets\Models\Warehouse;
use App\Modules\Assets\Support\Holder;
use App\Modules\Hr\Ports\StaffDirectory;
use App\Modules\Platform\Data\CompanyRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * Handovers on the web: staff issue stock to people and take it back; anyone can accept what was
 * handed to them (the app is the main place for that).
 */
class HandoverController extends Controller
{
    use RendersSuitePages, ScopesInventory;

    public function index(Request $request): Response
    {
        $companies = $this->companiesFor(AssetsFeature::Handovers, FeatureAction::View)->pluck('id');
        $warehouses = $this->warehousesFor(AssetsFeature::Handovers, FeatureAction::Create);
        $people = $this->people();

        return $this->suitePage('Assets/Inventory/Handovers', [
            'handovers' => Handover::query()->whereIn('company_id', $companies)
                ->when($request->query('status'), fn ($query, string $status) => $query->where('status', $status))
                ->withCount('lines')->latest('id')->paginate(30)->withQueryString()->through(fn (Handover $handover): array => self::summary($handover)),
            'filters' => ['status' => $request->query('status')],
            'statuses' => HandoverStatus::options(),
            'warehouses' => $warehouses->map(fn (Warehouse $warehouse): array => ['value' => 'warehouse:'.$warehouse->id, 'label' => $warehouse->localized('name'), 'company_id' => $warehouse->company_id])->values()->all(),
            'people' => array_map(fn (array $person): array => [...$person, 'value' => 'employee:'.$person['value']], $people),
            'catalog' => $this->catalog(),
            'stock' => $this->stockOf([...$warehouses->map(fn (Warehouse $warehouse): string => 'warehouse:'.$warehouse->id)->all(), ...array_map(fn (array $person): string => 'employee:'.$person['value'], $people)]),
            'assets' => $this->movableAssets($companies),
            'kinds' => IncidentKind::options(),
        ], ['assets']);
    }

    public function store(Request $request, ManageHandover $handovers): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'string', 'regex:/^(warehouse|employee):\d+$/'], 'to' => ['required', 'string', 'regex:/^(warehouse|employee):\d+$/', 'different:from'],
            'purpose' => ['nullable', 'string', 'max:255'], 'note' => ['nullable', 'string', 'max:255'],
            ...TransferController::lineRules(), 'lines' => ['nullable', 'array', 'max:200'],
            'write_offs' => ['nullable', 'array', 'max:50'], 'write_offs.*.variant_id' => ['nullable', 'integer'], 'write_offs.*.asset_id' => ['nullable', 'integer'],
            'write_offs.*.kind' => ['required', Rule::enum(IncidentKind::class)], 'write_offs.*.quantity' => ['nullable', 'numeric', 'gt:0'], 'write_offs.*.description' => ['nullable', 'string', 'max:500'],
            ...TransferController::photoRules(),
        ]);
        $from = Holder::of(...self::split($data['from']));
        $this->authorizeSender($from, $request->user());

        $handover = $handovers->create($from, Holder::of(...self::split($data['to'])), $data['lines'] ?? [], $data['write_offs'] ?? [], [...$data, 'channel' => 'web'], $request->file('photos', []), $request->user());

        return to_route('assets.inventory.handovers.show', $handover);
    }

    public function show(Request $request, Handover $handover): Response
    {
        abort_unless($this->companiesFor(AssetsFeature::Handovers, FeatureAction::View)->contains(fn (CompanyRef $company): bool => $company->id === $handover->company_id) || $this->isParty($handover, $request->user()), 403);

        return $this->suitePage('Assets/Inventory/Handover', [
            'handover' => self::summary($handover),
            'lines' => TransferController::lineArrays($handover->lines()->with(['variant.item.unit', 'asset'])->get()),
            'incidents' => $handover->incidents()->with(['variant.item', 'asset'])->get()->map(fn ($incident): array => IncidentController::summary($incident))->all(),
            'photos' => $handover->attachments()->get()->map(fn ($photo): array => ['id' => $photo->id, 'url' => $photo->temporaryUrl(), 'stage' => $photo->stage])->all(),
            'can' => ['accept' => $handover->status === HandoverStatus::Pending && $this->mayAccept($handover, $request->user()), 'cancel' => $handover->status === HandoverStatus::Pending && $handover->created_by === $request->user()->id],
            'kinds' => IncidentKind::options(),
        ], ['assets']);
    }

    public function accept(Request $request, Handover $handover, ManageHandover $handovers): RedirectResponse
    {
        abort_unless($this->mayAccept($handover, $request->user()), 403);
        $data = $request->validate([...TransferController::countRules(), ...TransferController::photoRules()]);

        $handovers->accept($handover, $data['counts'] ?? [], $data['explanations'] ?? [], $request->file('photos', []), $request->user());
        $this->toast(__('assets.handovers.accepted'));

        return back();
    }

    public function reject(Request $request, Handover $handover, ManageHandover $handovers): RedirectResponse
    {
        $cancelling = $handover->created_by === $request->user()->id && ! $this->mayAccept($handover, $request->user());
        abort_unless($cancelling || $this->mayAccept($handover, $request->user()), 403);

        $handovers->reject($handover, $request->validate(['reason' => ['nullable', 'string', 'max:255']])['reason'] ?? null, $cancelling ? HandoverStatus::Cancelled : HandoverStatus::Rejected, $request->user());
        $this->toast(__('assets.handovers.returned'));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(Handover $handover): array
    {
        $holders = app(Holders::class);

        return [
            'id' => $handover->id, 'number' => $handover->number, 'status' => $handover->status->value, 'purpose' => $handover->purpose, 'note' => $handover->note, 'channel' => $handover->channel,
            'from' => ['type' => $handover->from_type->value, 'id' => $handover->from_id, 'name' => $holders->name($handover->from_type->value, $handover->from_id)],
            'to' => ['type' => $handover->to_type->value, 'id' => $handover->to_id, 'name' => $holders->name($handover->to_type->value, $handover->to_id)],
            'lines' => $handover->lines_count ?? null, 'created_at' => $handover->created_at->toIso8601String(), 'accepted_at' => $handover->accepted_at?->toIso8601String(),
        ];
    }

    /** @return array{0: string, 1: int} */
    private static function split(string $holder): array
    {
        [$type, $id] = explode(':', $holder);

        return [$type, (int) $id];
    }

    private function employeeOf(Authenticatable $user): ?int
    {
        return app(StaffDirectory::class)->employeeForUser($user->getAuthIdentifier())?->id;
    }

    private function authorizeSender(Holder $from, Authenticatable $user): void
    {
        if ($from->type === HolderType::Employee && $from->id === $this->employeeOf($user)) {
            return;
        }

        $branchId = app(Holders::class)->describe($from)['branch_id'];
        $this->authorizeBranchFor(AssetsFeature::Handovers, FeatureAction::Create, $branchId);
    }

    /** The receiving employee, the receiving warehouse's keeper, or someone who may accept any handover there. */
    private function mayAccept(Handover $handover, Authenticatable $user): bool
    {
        $me = $this->employeeOf($user);
        $to = app(Holders::class)->describe(Holder::of($handover->to_type->value, $handover->to_id));

        return ($me !== null && $me === $to['employee_id'])
            || $this->access()->allowsBranch(AssetsFeature::Handovers->key('accept_any'), $to['branch_id'], $to['company_id']);
    }

    private function isParty(Handover $handover, Authenticatable $user): bool
    {
        $me = $this->employeeOf($user);

        return $me !== null && (($handover->from_type === HolderType::Employee && $handover->from_id === $me) || ($handover->to_type === HolderType::Employee && $handover->to_id === $me));
    }
}
