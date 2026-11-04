<?php

namespace App\Modules\Pos\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A menu section (Hot drinks, Burgers...). Optional brand for multi-brand companies.
 */
#[Table('pos__categories')]
#[Fillable(['company_id', 'brand_id', 'name_ar', 'name_en', 'color', 'sort_order', 'is_active'])]
class Category extends Model
{
    use BelongsToClient, HasBilingualFields;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
