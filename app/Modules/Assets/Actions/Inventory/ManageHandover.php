<?php

namespace App\Modules\Assets\Actions\Inventory;

use App\Modules\Assets\Actions\ManageCustody;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Enums\HandoverStatus;
use App\Modules\Assets\Enums\HolderType;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\Handover;
use App\Modules\Assets\Models\ItemVariant;
use App\Modules\Assets\Models\MovementLine;
use App\Modules\Assets\Models\Warehouse;
use App\Modules\Assets\Support\Holder;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Handovers (التسليم والاستلام): from a warehouse to a person, back to a warehouse, or between
 * people. The sender lists what goes (scanned or typed, with photos) and may write off part of
 * what they held ("26 of the 30 cups: 10 broken, 6 lost, 10 stolen"). The receiver counts what
 * arrived; anything short becomes an incident against the sender.
 */
class ManageHandover
{
    public function __construct(
        private Holders $holders,
        private MovementLines $lines,
        private RecordIncident $incidents,
        private StorePhotos $photos,
        private ManageCustody $custody,
        private NextNumber $numbers,
        private CurrentTenant $tenant,
        private Audit $audit,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  list<array{variant_id?: ?int, asset_id?: ?int, kind: string, quantity?: numeric-string|float|int|null, description?: ?string}>  $writeOffs
     * @param  array{purpose?: ?string, note?: ?string, latitude?: ?float, longitude?: ?float, channel?: string}  $details
     * @param  list<UploadedFile>  $photos
     */
    public function create(Holder $from, Holder $to, array $lines, array $writeOffs = [], array $details = [], array $photos = [], ?Authenticatable $actor = null): Handover
    {
        if ($from->is($to) || ($from->type === HolderType::Warehouse && $to->type === HolderType::Warehouse)) {
            throw ValidationException::withMessages(['to' => __('assets.errors.handover_holders')]);
        }

        $sender = $this->holders->describe($from);
        $receiver = $this->holders->describe($to);

        if ($sender['company_id'] !== $receiver['company_id'] || ! $receiver['active']) {
            throw ValidationException::withMessages(['to' => __('assets.errors.handover_company')]);
        }

        $prepared = $this->lines->prepare($lines, $from, $sender['company_id']);

        return DB::transaction(function () use ($from, $to, $sender, $prepared, $writeOffs, $details, $photos, $actor): Handover {
            $handover = (new Handover(collect($details)->only(['purpose', 'note'])->all()))->forceFill([
                'client_id' => $this->tenant->client()->id, 'company_id' => $sender['company_id'], 'number' => $this->numbers->for(Handover::class, 'HO'),
                'from_type' => $from->type->value, 'from_id' => $from->id, 'to_type' => $to->type->value, 'to_id' => $to->id,
                'latitude' => $details['latitude'] ?? null, 'longitude' => $details['longitude'] ?? null, 'channel' => $details['channel'] ?? 'web',
                'created_by' => $actor?->getAuthIdentifier(),
            ]);
            $handover->save();

            foreach ($writeOffs as $writeOff) {
                $this->incidents->handle(
                    ['kind' => $writeOff['kind'], 'quantity' => (string) ($writeOff['quantity'] ?? 1), 'description' => $writeOff['description'] ?? null],
                    ! empty($writeOff['variant_id']) ? ItemVariant::query()->whereHas('item')->findOrFail($writeOff['variant_id']) : null,
                    ! empty($writeOff['asset_id']) ? Asset::query()->findOrFail($writeOff['asset_id']) : null,
                    $from, 'handover', $handover->id, $sender['company_id'], $from->type === HolderType::Employee ? $from->id : $sender['employee_id'],
                    takeFromHolder: true, actor: $actor,
                );
            }

            $this->lines->dispatch($prepared, 'handover', $handover->id, $from, $actor);
            $this->photos->handle($photos, 'handover', $handover->id, 'sent', $actor);
            $this->audit->record($handover, 'created', ['from' => "{$from->type->value}:{$from->id}", 'to' => "{$to->type->value}:{$to->id}"], $actor);

            return $handover->load('lines');
        });
    }

    /**
     * The receiver confirms what arrived. Tagged assets move into the receiver's custody (or the
     * warehouse); quantities into their stock.
     *
     * @param  array<int, numeric-string|float|int>  $counts
     * @param  array<int, list<array{kind: string, quantity: numeric-string|float|int, description?: ?string}>>  $explanations
     * @param  list<UploadedFile>  $photos
     */
    public function accept(Handover $handover, array $counts = [], array $explanations = [], array $photos = [], ?Authenticatable $actor = null): Handover
    {
        $this->ensurePending($handover);
        $from = Holder::of($handover->from_type->value, $handover->from_id);
        $to = Holder::of($handover->to_type->value, $handover->to_id);
        $responsible = $from->type === HolderType::Employee ? $from->id : $this->holders->describe($from)['employee_id'];

        return DB::transaction(function () use ($handover, $counts, $explanations, $photos, $actor, $from, $to, $responsible): Handover {
            $arrived = $this->lines->settle($handover->lines()->with(['variant.item', 'asset.openCustody'])->get(), $counts, $explanations, 'handover', $handover->id, $to, $handover->company_id, $responsible, $actor);

            foreach ($arrived as $line) {
                $this->rehome($line, $from, $to, $actor);
            }

            $handover->forceFill(['status' => HandoverStatus::Accepted->value, 'accepted_by' => $actor?->getAuthIdentifier(), 'accepted_at' => now()])->save();
            $this->photos->handle($photos, 'handover', $handover->id, 'received', $actor);
            $this->audit->record($handover, 'accepted', null, $actor);

            return $handover;
        });
    }

    /** The receiver refuses (or the sender withdraws): everything goes back to the sender. */
    public function reject(Handover $handover, ?string $reason, HandoverStatus $as = HandoverStatus::Rejected, ?Authenticatable $actor = null): Handover
    {
        $this->ensurePending($handover);

        return DB::transaction(function () use ($handover, $reason, $as, $actor): Handover {
            $this->lines->returnToSender($handover->lines()->with('variant.item')->get(), 'handover', $handover->id, Holder::of($handover->from_type->value, $handover->from_id), $actor);
            $handover->forceFill(['status' => $as->value, 'note' => trim(($handover->note ? $handover->note.' · ' : '').($reason ?? ''), ' ·') ?: null])->save();
            $this->audit->record($handover, $as->value, ['reason' => $reason], $actor);

            return $handover;
        });
    }

    private function rehome(MovementLine $line, Holder $from, Holder $to, ?Authenticatable $actor): void
    {
        $asset = $line->asset;

        if ($from->type === HolderType::Employee && $asset->openCustody) {
            $this->custody->return($asset->openCustody, today()->toDateString(), $line->condition, $actor);
            $asset->refresh();
        }

        if ($to->type === HolderType::Employee) {
            $asset->forceFill(['status' => AssetStatus::Available->value])->save();
            $this->custody->assign($asset, $to->id, today()->toDateString(), $line->condition, $line->note, $actor);
        } else {
            $warehouse = Warehouse::query()->findOrFail($to->id);
            $asset->forceFill(['warehouse_id' => $warehouse->id, 'branch_id' => $warehouse->branch_id, 'status' => AssetStatus::Available->value])->save();
        }
    }

    private function ensurePending(Handover $handover): void
    {
        if ($handover->status !== HandoverStatus::Pending) {
            throw ValidationException::withMessages(['status' => __('assets.errors.handover_closed')]);
        }
    }
}
