<?php

namespace App\Modules\Pos\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A set of options a product can take (size, extras), with how many may be picked.
 */
#[Table('pos__modifier_groups')]
#[Fillable(['company_id', 'name_ar', 'name_en', 'min', 'max'])]
class ModifierGroup extends Model
{
    use BelongsToClient, HasBilingualFields;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['min' => 'integer', 'max' => 'integer'];
    }

    /**
     * @return HasMany<Modifier, $this>
     */
    public function modifiers(): HasMany
    {
        return $this->hasMany(Modifier::class, 'group_id')->orderBy('sort_order');
    }
}
