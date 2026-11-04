<?php

namespace App\Modules\Assets\Enums;

use App\Enums\Concerns\HasLabels;

enum WarehouseKind: string
{
    use HasLabels;

    const LABELS = 'warehouse_kind';

    case Main = 'main';
    case Store = 'store';
    case Vehicle = 'vehicle';
    case Event = 'event';

    protected static function labelFile(): string
    {
        return 'assets';
    }
}
