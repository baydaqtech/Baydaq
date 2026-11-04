<?php

namespace App\Modules\Platform\Enums;

use App\Enums\Concerns\HasLabels;

/**
 * Where in the tenant tree an assignment applies.
 */
enum ScopeLevel: string
{
    use HasLabels;

    const LABELS = 'scope_level';

    case Client = 'client';
    case Organization = 'organization';
    case Company = 'company';
    case Branch = 'branch';

    protected static function labelFile(): string
    {
        return 'suite';
    }
}
