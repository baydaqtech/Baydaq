<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum ChangeRequestStatus: string
{
    use HasLabels;

    const LABELS = 'change_request_status';

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
