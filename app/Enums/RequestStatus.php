<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum RequestStatus: string
{
    use HasLabels;

    const LABELS = 'request_status';

    case New = 'new';
    case Reviewing = 'reviewing';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
