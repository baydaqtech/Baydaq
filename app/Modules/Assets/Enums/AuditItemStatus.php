<?php

namespace App\Modules\Assets\Enums;

use App\Enums\Concerns\HasLabels;

enum AuditItemStatus: string
{
    use HasLabels;

    const LABELS = 'audit_item_status';

    case Expected = 'expected';
    case Found = 'found';
    case Missing = 'missing';
    case Unexpected = 'unexpected';

    protected static function labelFile(): string
    {
        return 'assets';
    }
}
