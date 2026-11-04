<?php

namespace App\Modules\Hr\Models;

use App\Modules\Hr\Models\Concerns\HasAudience;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A message the owner writes once and the system sends by hand, on a schedule or on an event.
 */
#[Table('hr__message_templates')]
#[Fillable(['name', 'trigger', 'event', 'weekday', 'month_day', 'days_before', 'channels', 'subject', 'body', 'is_active'])]
class MessageTemplate extends Model
{
    use BelongsToClient, HasAudience;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['channels' => 'array', 'is_active' => 'boolean', 'weekday' => 'integer', 'month_day' => 'integer', 'days_before' => 'integer', 'last_run_on' => 'date'];
    }
}
