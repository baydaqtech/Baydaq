<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum PunchType: string
{
    use HasLabels;

    const LABELS = 'punch_type';

    case In = 'in';
    case Out = 'out';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
