<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum RepaymentMethod: string
{
    use HasLabels;

    const LABELS = 'repayment_method';

    case Payroll = 'payroll';
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Settlement = 'settlement';
    case FinalSettlement = 'final_settlement';   // taken from the end-of-service award

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
