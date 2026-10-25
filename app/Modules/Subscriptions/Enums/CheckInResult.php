<?php

namespace App\Modules\Subscriptions\Enums;

use App\Enums\Concerns\HasLabels;

enum CheckInResult: string
{
    use HasLabels;

    const LABELS = 'check_in_result';

    case Allowed = 'allowed';
    case Repeat = 'repeat';
    case UnknownCard = 'unknown_card';
    case Inactive = 'inactive';
    case NoMembership = 'no_membership';
    case NotStarted = 'not_started';
    case Frozen = 'frozen';
    case NoSessions = 'no_sessions';
    case WrongBranch = 'wrong_branch';
    case WrongZone = 'wrong_zone';

    protected static function labelFile(): string
    {
        return 'subscriptions';
    }
}
