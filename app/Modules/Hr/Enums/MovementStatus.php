<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum MovementStatus: string
{
    use HasLabels;

    const LABELS = 'movement_status';

    case Scheduled = 'scheduled';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
