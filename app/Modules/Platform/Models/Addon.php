<?php

namespace App\Modules\Platform\Models;

use App\Models\Concerns\HasBilingualFields;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Something added on top of a package: a module, features, app screens or extra capacity
 * (limits are added per quantity bought). Not tenant data.
 */
#[Table('platform__addons')]
#[Fillable(['code', 'name_ar', 'name_en', 'price_monthly', 'modules', 'features', 'mobile', 'limits', 'is_active'])]
class Addon extends Model
{
    use HasBilingualFields;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['modules' => 'array', 'features' => 'array', 'mobile' => 'array', 'limits' => 'array', 'price_monthly' => 'decimal:2', 'is_active' => 'boolean'];
    }
}
