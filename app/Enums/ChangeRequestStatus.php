<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum ChangeRequestStatus: string
{
    use HasLabels;

    const LABELS = 'change_request_status';

    case Submitted = 'submitted';
    case Quoted = 'quoted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
}
