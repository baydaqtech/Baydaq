<?php

namespace App\Modules\Assets\Enums;

use App\Enums\Concerns\HasLabels;

enum IncidentKind: string
{
    use HasLabels;

    const LABELS = 'incident_kind';

    case Broken = 'broken';
    case Lost = 'lost';
    case Stolen = 'stolen';
    case Damaged = 'damaged';
    case Consumed = 'consumed';
    case Expired = 'expired';
    case Missing = 'missing';
    case Other = 'other';

    protected static function labelFile(): string
    {
        return 'assets';
    }
}
