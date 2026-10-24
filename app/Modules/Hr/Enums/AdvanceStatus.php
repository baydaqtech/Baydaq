<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum AdvanceStatus: string
{
    use HasLabels;

    const LABELS = 'advance_status';

    case Pending = 'pending';
    case Approved = 'approved';
    case Disbursed = 'disbursed';
    case Settled = 'settled';
    case Rejected = 'rejected';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
