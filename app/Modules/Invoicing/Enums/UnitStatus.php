<?php

namespace App\Modules\Invoicing\Enums;

use App\Enums\Concerns\HasLabels;

enum UnitStatus: string
{
    use HasLabels;

    const LABELS = 'unit_status';

    case New = 'new';
    case Compliance = 'compliance';
    case Production = 'production';
    case Revoked = 'revoked';

    protected static function labelFile(): string
    {
        return 'invoicing';
    }
}
