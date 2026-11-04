<?php

namespace App\Modules\Assets\Actions\Inventory;

use App\Modules\Assets\Models\ItemVariant;
use App\Modules\Assets\Models\Unit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

/**
 * Turns what a person typed into stock units: "2 cartons" of a 24-piece carton is 48 pieces,
 * "1.5 L" of an item counted in ml is 1500 ml. Units must share the item's dimension.
 */
class Quantities
{
    /**
     * @param  ?string  $unit  null for the item's own unit, "pack:{id}" or "unit:{id}"
     * @return array{0: string, 1: ?string} the quantity in stock units and how it was entered
     */
    public function toStockUnits(ItemVariant $variant, string $quantity, ?string $unit = null): array
    {
        $item = $variant->loadMissing(['item.unit', 'item.packs'])->item;
        $amount = BigDecimal::of($quantity);

        if ($unit === null || $unit === '' || $unit === 'unit:'.$item->unit_id) {
            return [(string) $amount->toScale(3, RoundingMode::HalfUp), null];
        }

        [$kind, $id] = array_pad(explode(':', $unit, 2), 2, null);

        if ($kind === 'pack' && ($pack = $item->packs->firstWhere('id', (int) $id))) {
            return [(string) $amount->multipliedBy((string) $pack->factor)->toScale(3, RoundingMode::HalfUp), $amount->strippedOfTrailingZeros().' × '.$pack->localized('name')];
        }

        $other = $kind === 'unit' ? Unit::query()->find((int) $id) : null;

        if ($other === null || $other->dimension !== $item->unit->dimension) {
            throw ValidationException::withMessages(['unit' => __('assets.errors.unit_mismatch', ['unit' => $item->unit->symbol])]);
        }

        $inStockUnits = $amount->multipliedBy((string) $other->factor)->dividedBy((string) $item->unit->factor, 3, RoundingMode::HalfUp);

        return [(string) $inStockUnits, $amount->strippedOfTrailingZeros().' '.$other->symbol];
    }
}
