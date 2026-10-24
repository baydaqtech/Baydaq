<?php

namespace App\Modules\Assets\Actions\Inventory;

use App\Modules\Assets\Models\ItemVariant;
use App\Modules\Assets\Models\Stock;
use App\Modules\Assets\Models\StockMove;
use App\Modules\Assets\Support\Holder;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only way stock changes. Each move takes a quantity from one holder and gives it to another
 * (either side may be outside the books), locks both rows, never lets a holder go below zero, and
 * writes a ledger line.
 */
class StockLedger
{
    public function __construct(private CurrentTenant $tenant) {}

    public function move(ItemVariant $variant, string $quantity, ?Holder $from, ?Holder $to, string $reason, ?string $documentType = null, ?int $documentId = null, ?Authenticatable $actor = null): void
    {
        $quantity = BigDecimal::of($quantity);

        if (! $quantity->isPositive()) {
            throw ValidationException::withMessages(['quantity' => __('assets.errors.quantity')]);
        }

        DB::transaction(function () use ($variant, $quantity, $from, $to, $reason, $documentType, $documentId, $actor): void {
            if ($from !== null) {
                $row = $this->row($from, $variant);
                $available = BigDecimal::of((string) $row->quantity);

                if ($available->isLessThan($quantity)) {
                    throw ValidationException::withMessages(['quantity' => __('assets.errors.not_enough', [
                        'item' => $variant->loadMissing('item')->label(), 'available' => (string) $available->strippedOfTrailingZeros(),
                    ])]);
                }

                $row->forceFill(['quantity' => (string) $available->minus($quantity)])->save();
            }

            if ($to !== null) {
                $row = $this->row($to, $variant);
                $row->forceFill(['quantity' => (string) BigDecimal::of((string) $row->quantity)->plus($quantity)])->save();
            }

            (new StockMove)->forceFill([
                'client_id' => $this->tenant->client()->id, 'variant_id' => $variant->id,
                'from_type' => $from?->type->value, 'from_id' => $from?->id, 'to_type' => $to?->type->value, 'to_id' => $to?->id,
                'quantity' => (string) $quantity, 'reason' => $reason, 'document_type' => $documentType, 'document_id' => $documentId,
                'user_id' => $actor?->getAuthIdentifier(), 'moved_at' => now(),
            ])->save();
        });
    }

    public function onHand(Holder $holder, int $variantId): string
    {
        return (string) (Stock::query()->where('holder_type', $holder->type->value)->where('holder_id', $holder->id)->where('variant_id', $variantId)->value('quantity') ?? '0');
    }

    private function row(Holder $holder, ItemVariant $variant): Stock
    {
        $row = Stock::query()->where('holder_type', $holder->type->value)->where('holder_id', $holder->id)->where('variant_id', $variant->id)->lockForUpdate()->first();

        if ($row === null) {
            $row = (new Stock)->forceFill(['client_id' => $this->tenant->client()->id, 'holder_type' => $holder->type->value, 'holder_id' => $holder->id, 'variant_id' => $variant->id, 'quantity' => '0']);
            $row->save();
        }

        return $row;
    }
}
