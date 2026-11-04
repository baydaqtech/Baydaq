<?php

namespace App\Modules\Pos\Enums;

use App\Enums\Concerns\HasLabels;

/**
 * How a refund went back to the customer, and where it leaves the books.
 */
enum RefundMethod: string
{
    use HasLabels;

    const LABELS = 'refund_method';

    case Cash = 'cash';     // from the till of the open shift
    case Card = 'card';     // reversed on the card terminal
    case Wallet = 'wallet'; // into the customer's wallet

    public function ledgerAccount(): string
    {
        return match ($this) {
            self::Cash => 'cash',
            self::Card => 'card_clearing',
            self::Wallet => 'customer_wallets',
        };
    }

    /** UN/ECE 4461 code for the credit note. */
    public function zatcaCode(): string
    {
        return match ($this) {
            self::Cash => '10',
            self::Card => '48',
            self::Wallet => '1',
        };
    }

    protected static function labelFile(): string
    {
        return 'pos';
    }
}
