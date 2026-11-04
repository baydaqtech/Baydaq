<?php

namespace App\Modules\Platform\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * One movement of a customer's loyalty points. Never edited: a correction is a new entry.
 */
#[Table('platform__point_entries')]
class PointEntry extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['points' => 'integer', 'balance_after' => 'integer'];
    }
}
