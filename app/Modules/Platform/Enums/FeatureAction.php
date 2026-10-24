<?php

namespace App\Modules\Platform\Enums;

use App\Enums\Concerns\HasLabels;

/**
 * The standard actions a feature can offer. Anything else is a feature-specific extra ability.
 */
enum FeatureAction: string
{
    use HasLabels;

    const LABELS = 'feature_action';

    case View = 'view';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Export = 'export';
    case Approve = 'approve';

    protected static function labelFile(): string
    {
        return 'suite';
    }
}
