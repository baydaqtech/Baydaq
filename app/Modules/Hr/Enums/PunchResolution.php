<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum PunchResolution: string
{
    use HasLabels;

    const LABELS = 'punch_resolution';

    case Accepted = 'accepted';
    case Rejected = 'rejected';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
