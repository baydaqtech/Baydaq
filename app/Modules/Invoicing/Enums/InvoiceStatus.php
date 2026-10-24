<?php

namespace App\Modules\Invoicing\Enums;

use App\Enums\Concerns\HasLabels;

enum InvoiceStatus: string
{
    use HasLabels;

    const LABELS = 'invoice_status';

    case Draft = 'draft';
    case Issued = 'issued';
    case Cleared = 'cleared';
    case Reported = 'reported';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    protected static function labelFile(): string
    {
        return 'invoicing';
    }
}
