<?php

namespace App\Modules\Assets\Enums;

use App\Enums\Concerns\HasLabels;

enum HolderType: string
{
    use HasLabels;

    const LABELS = 'holder_type';

    case Warehouse = 'warehouse';
    case Employee = 'employee';
    case Transfer = 'transfer';
    case Handover = 'handover';

    protected static function labelFile(): string
    {
        return 'assets';
    }
}
