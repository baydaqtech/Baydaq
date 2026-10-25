<?php

namespace App\Modules\Subscriptions\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use App\Modules\Subscriptions\Enums\PlanGender;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * An area of a branch with its own door: women only, men only, or anyone (S2).
 */
#[Table('sub__zones')]
#[Fillable(['branch_id', 'name_ar', 'name_en', 'gender'])]
class Zone extends Model
{
    use BelongsToClient, HasBilingualFields;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['gender' => PlanGender::class];
    }
}
