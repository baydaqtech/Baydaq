<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum AllowanceCalculation: string
{
    use HasLabels;

    const LABELS = 'allowance_calculation';

    case Fixed = 'fixed';
    case PercentOfBasic = 'percent_of_basic';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
