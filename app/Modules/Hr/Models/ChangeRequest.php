<?php

namespace App\Modules\Hr\Models;

use App\Modules\Hr\Enums\ChangeRequestStatus;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sensitive change (IBAN, salary) held until a second person approves it. The new values
 * stay encrypted in payload; summary carries only masked before/after for display.
 */
#[Table('hr__change_requests')]
class ChangeRequest extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'summary' => 'array',
            'status' => ChangeRequestStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
