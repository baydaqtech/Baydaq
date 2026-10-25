<?php

namespace App\Modules\Pos\Enums;

use App\Enums\Concerns\HasLabels;

enum PaymentMethod: string
{
    use HasLabels;

    const LABELS = 'payment_method';

    case Cash = 'cash';
    case Mada = 'mada';
    case Card = 'card';
    case StcPay = 'stc_pay';
    case Transfer = 'transfer';
    case StaffAccount = 'staff_account'; // a staff meal taken from the employee's pay (P3)

    /** UN/ECE 4461 code for the e-invoice. */
    public function zatcaCode(): string
    {
        return match ($this) {
            self::Cash => '10',
            self::Transfer => '42',
            self::StaffAccount => '30',
            default => '48',
        };
    }

    protected static function labelFile(): string
    {
        return 'pos';
    }
}
