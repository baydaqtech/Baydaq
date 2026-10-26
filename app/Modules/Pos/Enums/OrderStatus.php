<?php

namespace App\Modules\Pos\Enums;

use App\Enums\Concerns\HasLabels;

enum OrderStatus: string
{
    use HasLabels;

    const LABELS = 'order_status';

    case Open = 'open';
    case Paid = 'paid';
    case Void = 'void';

    protected static function labelFile(): string
    {
        return 'pos';
    }
}
