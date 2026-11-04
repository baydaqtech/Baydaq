<?php

namespace App\Modules\Pos\Enums;

use App\Enums\Concerns\HasLabels;

enum KitchenStatus: string
{
    use HasLabels;

    const LABELS = 'kitchen_status';

    case New = 'new';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';

    protected static function labelFile(): string
    {
        return 'pos';
    }
}
