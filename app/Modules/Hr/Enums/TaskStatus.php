<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum TaskStatus: string
{
    use HasLabels;

    const LABELS = 'task_status';

    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
