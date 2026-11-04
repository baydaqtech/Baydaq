<?php

namespace App\Modules\Assets\Models;

use App\Models\Concerns\HasBilingualFields;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A package of an item, typed in transfers and handovers: Carton = 24 pieces.
 */
#[Table('ast__item_packs')]
#[Fillable(['name_ar', 'name_en', 'factor'])]
class ItemPack extends Model
{
    use HasBilingualFields;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['factor' => 'decimal:3'];
    }
}
