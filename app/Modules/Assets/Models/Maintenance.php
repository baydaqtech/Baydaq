<?php

namespace App\Modules\Assets\Models;

use App\Modules\Assets\Enums\MaintenanceKind;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A repair or service job on an asset, open until closed.
 */
#[Table('ast__maintenance')]
class Maintenance extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => MaintenanceKind::class, 'opened_on' => 'date', 'closed_on' => 'date', 'cost' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
