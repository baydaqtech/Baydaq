<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum TaskPriority: string
{
    use HasLabels;

    const LABELS = 'task_priority';

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
