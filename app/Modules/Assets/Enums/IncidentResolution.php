<?php

namespace App\Modules\Assets\Enums;

use App\Enums\Concerns\HasLabels;

enum IncidentResolution: string
{
    use HasLabels;

    const LABELS = 'incident_resolution';

    case WrittenOff = 'written_off';
    case Charged = 'charged';
    case Recovered = 'recovered';
    case Insurance = 'insurance';
    case Other = 'other';

    protected static function labelFile(): string
    {
        return 'assets';
    }
}
