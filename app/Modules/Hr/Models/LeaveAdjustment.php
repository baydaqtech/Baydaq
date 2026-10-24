<?php

namespace App\Modules\Hr\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A manual change to a leave balance: an opening balance carried from the previous system,
 * a correction, or days granted. Counts in the leave year of its effective date.
 */
#[Table('hr__leave_adjustments')]
class LeaveAdjustment extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['days' => 'decimal:2', 'effective_on' => 'date'];
    }
}
