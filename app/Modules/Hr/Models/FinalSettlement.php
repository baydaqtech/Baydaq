<?php

namespace App\Modules\Hr\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a leaver was paid at the end: the award, advances recovered from it, and the net.
 */
#[Table('hr__final_settlements')]
class FinalSettlement extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_day' => 'date',
            'eosb_award' => 'decimal:2',
            'advances_recovered' => 'decimal:2',
            'advances_left' => 'decimal:2',
            'net_payable' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
