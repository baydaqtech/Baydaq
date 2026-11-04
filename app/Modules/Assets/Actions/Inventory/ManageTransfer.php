<?php

namespace App\Modules\Assets\Actions\Inventory;

use App\Modules\Assets\Enums\TransferStatus;
use App\Modules\Assets\Models\Transfer;
use App\Modules\Assets\Models\Warehouse;
use App\Modules\Assets\Support\Holder;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transfers between warehouses (of any company of the owner): drafted, sent (the stock leaves the
 * source and is in transit), then counted in at the destination. Shortfalls become incidents.
 */
class ManageTransfer
{
    public function __construct(
        private MovementLines $lines,
        private StockLedger $ledger,
        private StorePhotos $photos,
        private NextNumber $numbers,
        private CurrentTenant $tenant,
        private Audit $audit,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function create(Warehouse $from, Warehouse $to, array $lines, ?string $note = null, ?Authenticatable $actor = null): Transfer
    {
        if ($from->is($to) || ! $to->is_active) {
            throw ValidationException::withMessages(['to_warehouse_id' => __('assets.errors.transfer_warehouses')]);
        }

        $prepared = $this->lines->prepare($lines, Holder::warehouse($from->id), $from->company_id);

        return DB::transaction(function () use ($from, $to, $prepared, $note, $actor): Transfer {
            $transfer = (new Transfer(['note' => $note]))->forceFill([
                'client_id' => $this->tenant->client()->id, 'number' => $this->numbers->for(Transfer::class, 'TR'),
                'from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id, 'created_by' => $actor?->getAuthIdentifier(),
            ]);
            $transfer->save();

            // Lines are written now; stock only leaves when the transfer is sent.
            foreach ($prepared as $line) {
                $transfer->lines()->make()->forceFill([
                    'document_type' => 'transfer', 'variant_id' => $line['variant']?->id, 'asset_id' => $line['asset']?->id,
                    'quantity' => $line['quantity'], 'entered_unit' => $line['entered'], 'condition' => $line['condition'], 'note' => $line['note'],
                ])->save();
            }

            $this->audit->record($transfer, 'created', null, $actor);

            return $transfer->load('lines');
        });
    }

    /**
     * @param  list<UploadedFile>  $photos
     */
    public function send(Transfer $transfer, array $photos = [], ?Authenticatable $actor = null): Transfer
    {
        $this->ensure($transfer, TransferStatus::Draft);

        return DB::transaction(function () use ($transfer, $photos, $actor): Transfer {
            $from = Holder::warehouse($transfer->from_warehouse_id);
            $inTransit = Holder::of('transfer', $transfer->id);

            foreach ($transfer->lines()->with(['variant.item', 'asset'])->get() as $line) {
                if ($line->variant_id) {
                    $this->ledger->move($line->variant, (string) $line->quantity, $from, $inTransit, 'transfer', 'transfer', $transfer->id, $actor);
                } elseif ($line->asset->warehouse_id !== $transfer->from_warehouse_id) {
                    throw ValidationException::withMessages(['lines' => __('assets.errors.asset_not_held', ['tag' => $line->asset->tag])]);
                }
            }

            $transfer->forceFill(['status' => TransferStatus::InTransit->value, 'sent_at' => now(), 'sent_by' => $actor?->getAuthIdentifier()])->save();
            $this->photos->handle($photos, 'transfer', $transfer->id, 'sent', $actor);
            $this->audit->record($transfer, 'sent', null, $actor);

            return $transfer;
        });
    }

    /**
     * @param  array<int, numeric-string|float|int>  $counts
     * @param  array<int, list<array{kind: string, quantity: numeric-string|float|int, description?: ?string}>>  $explanations
     * @param  list<UploadedFile>  $photos
     */
    public function receive(Transfer $transfer, array $counts = [], array $explanations = [], array $photos = [], ?Authenticatable $actor = null): Transfer
    {
        $this->ensure($transfer, TransferStatus::InTransit);
        $to = Warehouse::query()->findOrFail($transfer->to_warehouse_id);
        $keeper = Warehouse::query()->whereKey($transfer->from_warehouse_id)->value('keeper_employee_id');

        return DB::transaction(function () use ($transfer, $counts, $explanations, $photos, $actor, $to, $keeper): Transfer {
            $arrived = $this->lines->settle($transfer->lines()->with(['variant.item', 'asset'])->get(), $counts, $explanations, 'transfer', $transfer->id, Holder::warehouse($to->id), $to->company_id, $keeper, $actor);

            foreach ($arrived as $line) {
                $line->asset->forceFill(['warehouse_id' => $to->id, 'branch_id' => $to->branch_id, 'company_id' => $to->company_id])->save();
            }

            $transfer->forceFill(['status' => TransferStatus::Received->value, 'received_at' => now(), 'received_by' => $actor?->getAuthIdentifier()])->save();
            $this->photos->handle($photos, 'transfer', $transfer->id, 'received', $actor);
            $this->audit->record($transfer, 'received', null, $actor);

            return $transfer;
        });
    }

    /** A draft is simply dropped; a transfer in transit goes back to the source. */
    public function cancel(Transfer $transfer, ?Authenticatable $actor = null): Transfer
    {
        if (! in_array($transfer->status, [TransferStatus::Draft, TransferStatus::InTransit], true)) {
            throw ValidationException::withMessages(['status' => __('assets.errors.transfer_closed')]);
        }

        return DB::transaction(function () use ($transfer, $actor): Transfer {
            if ($transfer->status === TransferStatus::InTransit) {
                $this->lines->returnToSender($transfer->lines()->with('variant.item')->get(), 'transfer', $transfer->id, Holder::warehouse($transfer->from_warehouse_id), $actor);
            }

            $transfer->forceFill(['status' => TransferStatus::Cancelled->value])->save();
            $this->audit->record($transfer, 'cancelled', null, $actor);

            return $transfer;
        });
    }

    private function ensure(Transfer $transfer, TransferStatus $expected): void
    {
        if ($transfer->status !== $expected) {
            throw ValidationException::withMessages(['status' => __('assets.errors.transfer_closed')]);
        }
    }
}
