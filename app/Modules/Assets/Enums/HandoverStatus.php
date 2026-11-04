<?php

namespace App\Modules\Assets\Enums;

use App\Enums\Concerns\HasLabels;

enum HandoverStatus: string
{
    use HasLabels;

    const LABELS = 'handover_status';

    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    protected static function labelFile(): string
    {
        return 'assets';
    }
}
