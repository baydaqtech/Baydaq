<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum LeaveStatus: string
{
    use HasLabels;

    const LABELS = 'leave_status';

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
