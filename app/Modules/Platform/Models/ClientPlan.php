<?php

namespace App\Modules\Platform\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The plan a client is on. Written by ApplyPlan only; read through the Entitlements port.
 */
#[Table('platform__client_plans')]
class ClientPlan extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['addons' => 'array', 'overrides' => 'array', 'starts_on' => 'date', 'ends_on' => 'date'];
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function isUsable(): bool
    {
        return $this->status !== 'suspended' && ($this->ends_on === null || ! $this->ends_on->isPast());
    }
}
