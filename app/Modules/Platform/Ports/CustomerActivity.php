<?php

namespace App\Modules\Platform\Ports;

use App\Modules\Platform\Data\CustomerActivityItem;
use App\Modules\Platform\Data\ScopeContext;

/**
 * Modules show what a customer did with them on the customer profile by tagging an
 * implementation 'customer.activity'. Each lists only what the viewer may see.
 */
interface CustomerActivity
{
    public const TAG = 'customer.activity';

    /** @return list<CustomerActivityItem> newest first, at most $limit */
    public function activity(int $customerId, ScopeContext $context, int $limit = 20): array;
}
