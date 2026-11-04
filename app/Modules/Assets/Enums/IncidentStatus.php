<?php

namespace App\Modules\Assets\Enums;

use App\Enums\Concerns\HasLabels;

enum IncidentStatus: string
{
    use HasLabels;

    const LABELS = 'incident_status';

    case Open = 'open';
    case Resolved = 'resolved';

    protected static function labelFile(): string
    {
        return 'assets';
    }
}
