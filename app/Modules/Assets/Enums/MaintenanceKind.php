<?php

namespace App\Modules\Assets\Enums;

use App\Enums\Concerns\HasLabels;

enum MaintenanceKind: string
{
    use HasLabels;

    const LABELS = 'maintenance_kind';

    case Repair = 'repair';
    case Preventive = 'preventive';

    protected static function labelFile(): string
    {
        return 'assets';
    }
}
