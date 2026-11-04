<?php

namespace App\Modules\Pos\Models;

use App\Models\Concerns\HasBilingualFields;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table('pos__modifiers')]
#[Fillable(['name_ar', 'name_en', 'price', 'sort_order', 'is_active'])]
class Modifier extends Model
{
    use HasBilingualFields;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_active' => 'boolean'];
    }
}
