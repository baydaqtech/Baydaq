<?php

namespace App\Modules\Hr\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A public holiday or the Ramadan period of a tenant (dates follow the official announcement).
 */
#[Table('hr__holidays')]
#[Fillable(['name_ar', 'name_en', 'kind', 'starts_on', 'ends_on'])]
class Holiday extends Model
{
    use BelongsToClient, HasBilingualFields;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public static function covering(Carbon $date, string $kind): ?self
    {
        return self::query()->where('kind', $kind)->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->first();
    }
}
