<?php

namespace App\Modules\Platform\Models;

use App\Models\Concerns\HasBilingualFields;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A package Baydaq sells: modules, features, employee-app screens and limits. Not tenant data.
 */
#[Table('platform__packages')]
#[Fillable(['code', 'name_ar', 'name_en', 'description_ar', 'description_en', 'price_monthly', 'price_yearly', 'trial_days', 'modules', 'features', 'mobile', 'limits', 'is_public', 'is_active', 'sort_order'])]
class Package extends Model
{
    use HasBilingualFields;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'modules' => 'array', 'features' => 'array', 'mobile' => 'array', 'limits' => 'array',
            'price_monthly' => 'decimal:2', 'price_yearly' => 'decimal:2', 'trial_days' => 'integer',
            'is_public' => 'boolean', 'is_active' => 'boolean', 'sort_order' => 'integer',
        ];
    }
}
