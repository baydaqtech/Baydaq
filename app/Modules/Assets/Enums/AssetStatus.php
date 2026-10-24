<?php

namespace App\Modules\Assets\Enums;

use App\Enums\Concerns\HasLabels;

enum AssetStatus: string
{
    use HasLabels;

    const LABELS = 'asset_status';

    case Available = 'available';
    case Assigned = 'assigned';
    case Maintenance = 'maintenance';
    case Lost = 'lost';
    case Disposed = 'disposed';

    protected static function labelFile(): string
    {
        return 'assets';
    }
}
