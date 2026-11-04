<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum MovementType: string
{
    use HasLabels;

    const LABELS = 'movement_type';

    /** Permanent change of home branch, within the same company. */
    case Transfer = 'transfer';

    /** Temporary work at a host branch (possibly in another company of the owner); the home branch keeps the salary. */
    case Secondment = 'secondment';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
