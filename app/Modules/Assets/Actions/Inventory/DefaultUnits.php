<?php

namespace App\Modules\Assets\Actions\Inventory;

use App\Modules\Assets\Models\Unit;
use App\Modules\Platform\Tenancy\CurrentTenant;

class DefaultUnits
{
    public function __construct(private CurrentTenant $tenant) {}

    /** Give an owner the standard units (piece, dozen, ml, L, g, kg, mm, cm, m, km) the first time. */
    public function ensure(): void
    {
        if (Unit::query()->exists()) {
            return;
        }

        foreach (Unit::defaults() as [$ar, $en, $symbol, $dimension, $factor]) {
            (new Unit(['name_ar' => $ar, 'name_en' => $en, 'symbol' => $symbol, 'dimension' => $dimension, 'factor' => $factor]))->forceFill(['client_id' => $this->tenant->client()->id])->save();
        }
    }
}
