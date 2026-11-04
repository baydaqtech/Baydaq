<?php

namespace App\Modules\Hr\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The one phone an employee may punch from. Rebinding needs the reset_device ability.
 */
#[Table('hr__attendance_devices')]
class AttendanceDevice extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['bound_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
