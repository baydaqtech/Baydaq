<?php

namespace App\Modules\Subscriptions\Enums;

use App\Enums\Concerns\HasLabels;

enum MembershipStatus: string
{
    use HasLabels;

    const LABELS = 'membership_status';

    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    protected static function labelFile(): string
    {
        return 'subscriptions';
    }
}
