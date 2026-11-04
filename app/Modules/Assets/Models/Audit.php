<?php

namespace App\Modules\Assets\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A stock-take at a branch: the assets expected there when it started, ticked off as scanned.
 */
#[Table('ast__audits')]
class Audit extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['started_on' => 'date', 'closed_at' => 'datetime'];
    }

    /**
     * @return HasMany<AuditItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(AuditItem::class);
    }
}
