<?php

namespace App\Modules\Pos\Enums;

use App\Enums\Concerns\HasLabels;

enum OrderType: string
{
    use HasLabels;

    const LABELS = 'order_type';

    case DineIn = 'dine_in';
    case Takeaway = 'takeaway';
    case Delivery = 'delivery';

    protected static function labelFile(): string
    {
        return 'pos';
    }
}
