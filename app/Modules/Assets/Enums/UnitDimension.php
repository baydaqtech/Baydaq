<?php

namespace App\Modules\Assets\Enums;

use App\Enums\Concerns\HasLabels;

enum UnitDimension: string
{
    use HasLabels;

    const LABELS = 'unit_dimension';

    case Count = 'count';
    case Volume = 'volume';
    case Weight = 'weight';
    case Length = 'length';

    protected static function labelFile(): string
    {
        return 'assets';
    }
}
