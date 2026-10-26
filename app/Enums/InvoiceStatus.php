<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum InvoiceStatus: string
{
    use HasLabels;

    const LABELS = 'invoice_status';

    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Void = 'void';
}
