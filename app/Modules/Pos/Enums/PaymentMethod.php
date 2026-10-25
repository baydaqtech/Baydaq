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
    case Wallet = 'wallet';              // the customer's account-wide wallet (X2)

    /** UN/ECE 4461 code for the e-invoice. */
    public function zatcaCode(): string
    {
        return match ($this) {
            self::Cash => '10',
            self::Transfer => '42',
            self::StaffAccount => '30',
            self::Wallet => '1',
            default => '48',
        };
    }

    protected static function labelFile(): string
    {
        return 'pos';
    }
}
