<?php

namespace App\Modules\Invoicing\Enums;

use App\Enums\Concerns\HasLabels;

enum InvoiceKind: string
{
    use HasLabels;

    const LABELS = 'invoice_kind';

    case Invoice = 'invoice';
    case CreditNote = 'credit_note';
    case DebitNote = 'debit_note';

    /** UN/CEFACT 1001 code ZATCA expects. */
    public function typeCode(): string
    {
        return match ($this) {
            self::Invoice => '388',
            self::CreditNote => '381',
            self::DebitNote => '383',
        };
    }

    protected static function labelFile(): string
    {
        return 'invoicing';
    }
}
