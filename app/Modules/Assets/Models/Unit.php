<?php

namespace App\Modules\Assets\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Assets\Enums\UnitDimension;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A unit of measure: piece, dozen, ml, L, g, kg, mm, m, km… Units of one dimension convert
 * through their factor (in the dimension's smallest unit: 1 L = 1000 ml).
 */
#[Table('ast__units')]
#[Fillable(['name_ar', 'name_en', 'symbol', 'dimension', 'factor', 'is_active'])]
class Unit extends Model
{
    use BelongsToClient, HasBilingualFields;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['factor' => 1, 'is_active' => true];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['dimension' => UnitDimension::class, 'factor' => 'decimal:6', 'is_active' => 'boolean'];
    }

    /**
     * The units every owner starts with.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    public static function defaults(): array
    {
        return [
            ['قطعة', 'Piece', 'pc', 'count', '1'], ['درزن', 'Dozen', 'dz', 'count', '12'],
            ['مليلتر', 'Millilitre', 'ml', 'volume', '1'], ['لتر', 'Litre', 'L', 'volume', '1000'],
            ['غرام', 'Gram', 'g', 'weight', '1'], ['كيلوغرام', 'Kilogram', 'kg', 'weight', '1000'],
            ['مليمتر', 'Millimetre', 'mm', 'length', '1'], ['سنتيمتر', 'Centimetre', 'cm', 'length', '10'],
            ['متر', 'Metre', 'm', 'length', '1000'], ['كيلومتر', 'Kilometre', 'km', 'length', '1000000'],
        ];
    }
}
