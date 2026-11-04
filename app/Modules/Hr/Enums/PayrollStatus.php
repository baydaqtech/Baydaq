<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum PayrollStatus: string
{
    use HasLabels;

    const LABELS = 'payroll_status';

    case Draft = 'draft';
    case Approved = 'approved';
    case Paid = 'paid';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
