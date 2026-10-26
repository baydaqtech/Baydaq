<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum PlacementKind: string
{
    use HasLabels;

    const LABELS = 'placement_kind';

    case Home = 'home';
    case Secondment = 'secondment';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
