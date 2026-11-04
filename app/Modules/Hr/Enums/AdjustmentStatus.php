<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum AdjustmentStatus: string
{
    use HasLabels;

    const LABELS = 'adjustment_status';

    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';
    case Rejected = 'rejected';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
