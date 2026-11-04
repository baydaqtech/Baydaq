<?php

namespace App\Modules\Assets\Actions\Inventory;

use App\Modules\Assets\Models\ItemVariant;
use App\Modules\Assets\Models\Stock;
use App\Modules\Assets\Models\Warehouse;
use App\Modules\Assets\Support\Holder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

class ReceiveStock
{
    public function __construct(private StockLedger $ledger, private Quantities $quantities) {}

    /**
     * Stock arriving from outside (a purchase, opening stock). A unit cost updates the variant's
     * cost as a weighted average over everything on hand.
     *
     * @param  list<array{variant_id: int, quantity: numeric-string|float|int, unit?: ?string, unit_cost?: numeric-string|float|int|null}>  $lines
     */
    public function handle(Warehouse $warehouse, array $lines, ?Authenticatable $actor = null): void
    {
        DB::transaction(function () use ($warehouse, $lines, $actor): void {
            foreach ($lines as $line) {
                $variant = ItemVariant::query()->whereHas('item')->findOrFail($line['variant_id']);
                [$quantity] = $this->quantities->toStockUnits($variant, (string) $line['quantity'], $line['unit'] ?? null);

                if (isset($line['unit_cost']) && $line['unit_cost'] !== '') {
                    $onHand = BigDecimal::of((string) Stock::query()->where('variant_id', $variant->id)->sum('quantity'));
                    $total = $onHand->plus($quantity);
                    $cost = $onHand->multipliedBy((string) $variant->unit_cost)->plus(BigDecimal::of($quantity)->multipliedBy((string) $line['unit_cost']))
                        ->dividedBy($total, 4, RoundingMode::HalfUp);
                    $variant->forceFill(['unit_cost' => (string) $cost])->save();
                }

                $this->ledger->move($variant, $quantity, null, Holder::warehouse($warehouse->id), 'receipt', null, null, $actor);
            }
        });
    }
}
