<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum ContractStatus: string
{
    use HasLabels;

    const LABELS = 'contract_status';

    case Draft = 'draft';
    case Active = 'active';
    case Ended = 'ended';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
