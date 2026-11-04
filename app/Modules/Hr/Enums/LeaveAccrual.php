<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum LeaveAccrual: string
{
    use HasLabels;

    const LABELS = 'leave_accrual';

    case Yearly = 'yearly';
    case Monthly = 'monthly';
    case Daily = 'daily';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
