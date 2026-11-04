<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum BreakStatus: string
{
    use HasLabels;

    const LABELS = 'break_status';

    case Requested = 'requested';
    case OnBreak = 'on_break';
    case Ended = 'ended';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
