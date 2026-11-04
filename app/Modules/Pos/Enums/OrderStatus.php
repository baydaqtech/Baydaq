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
    case Refunded = 'refunded'; // every line came back

    protected static function labelFile(): string
    {
        return 'pos';
    }
}
