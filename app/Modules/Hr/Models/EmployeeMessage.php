<?php

namespace App\Modules\Hr\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A message an employee received (their inbox), with the channels it went out on.
 */
#[Table('hr__messages')]
class EmployeeMessage extends Model
{
    use BelongsToClient;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['channels' => 'array', 'sent_at' => 'datetime', 'read_at' => 'datetime'];
    }
}
