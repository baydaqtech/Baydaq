<?php

namespace App\Modules\Hr\Models;

use App\Modules\Hr\Enums\PunchFlag;
use App\Modules\Hr\Enums\PunchResolution;
use App\Modules\Hr\Enums\PunchType;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One check-in or check-out, with the location match and the flag the server decided.
 */
#[Table('hr__attendance_punches')]
class AttendancePunch extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PunchType::class,
            'flag' => PunchFlag::class,
            'resolution' => PunchResolution::class,
            'punched_at' => 'datetime',
            'is_remote' => 'boolean',
            'work_date' => 'date',
            'resolved_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy_m' => 'integer',
            'mocked' => 'boolean',
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
