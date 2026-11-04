<?php

namespace App\Modules\Assets\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Laptops, vehicles, furniture...: one list for the whole owner, with a default useful life.
 */
#[Table('ast__categories')]
#[Fillable(['name_ar', 'name_en', 'useful_life_months', 'depreciates', 'is_active'])]
class Category extends Model
{
    use BelongsToClient, HasBilingualFields;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['useful_life_months' => 60, 'depreciates' => true, 'is_active' => true];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['useful_life_months' => 'integer', 'depreciates' => 'boolean', 'is_active' => 'boolean'];
    }
}
