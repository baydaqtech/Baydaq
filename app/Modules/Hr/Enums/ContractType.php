<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum ContractType: string
{
    use HasLabels;

    const LABELS = 'contract_type';

    case Fixed = 'fixed';
    case OpenEnded = 'open_ended';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
