<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum LeaveYearBasis: string
{
    use HasLabels;

    const LABELS = 'leave_year_basis';

    case Calendar = 'calendar';
    case HireDate = 'hire_date';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
