<?php

namespace App\Modules\Platform\Enums;

use App\Enums\Concerns\HasLabels;

enum BillingUnit: string
{
    use HasLabels;

    const LABELS = 'billing_unit';

    case Seat = 'seat';
    case Register = 'register';
    case Company = 'company';
    case Branch = 'branch';

    protected static function labelFile(): string
    {
        return 'suite';
    }
}
