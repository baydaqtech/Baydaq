<?php

namespace App\Modules\Assets\Enums;

use App\Enums\Concerns\HasLabels;

enum TransferStatus: string
{
    use HasLabels;

    const LABELS = 'transfer_status';

    case Draft = 'draft';
    case InTransit = 'in_transit';
    case Received = 'received';
    case Cancelled = 'cancelled';

    protected static function labelFile(): string
    {
        return 'assets';
    }
}
