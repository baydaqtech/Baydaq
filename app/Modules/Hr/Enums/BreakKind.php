<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum BreakKind: string
{
    use HasLabels;

    const LABELS = 'break_kind';

    case Scheduled = 'scheduled';
    case Granted = 'granted';
    case Requested = 'requested';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
