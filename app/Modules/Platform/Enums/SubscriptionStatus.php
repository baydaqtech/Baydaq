<?php

namespace App\Modules\Platform\Enums;

use App\Enums\Concerns\HasLabels;

enum SubscriptionStatus: string
{
    use HasLabels;

    const LABELS = 'subscription_status';

    case Active = 'active';

    /** Past its end date: the module opens read-only until it is renewed. */
    case Lapsed = 'lapsed';

    case Cancelled = 'cancelled';

    protected static function labelFile(): string
    {
        return 'suite';
    }
}
