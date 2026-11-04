<?php

namespace App\Modules\Invoicing\Enums;

use App\Enums\Concerns\HasLabels;

enum InvoiceType: string
{
    use HasLabels;

    const LABELS = 'invoice_type';

    /** B2B / B2G: cleared by ZATCA before it reaches the buyer. */
    case Standard = 'standard';

    /** B2C: reported to ZATCA within 24 hours. */
    case Simplified = 'simplified';

    /** ZATCA invoice type code (BT-3) and the subtype bitmap (01 standard / 02 simplified). */
    public function subtypeCode(): string
    {
        return $this === self::Standard ? '0100000' : '0200000';
    }

    protected static function labelFile(): string
    {
        return 'invoicing';
    }
}
