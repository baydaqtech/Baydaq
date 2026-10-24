<?php

namespace App\Modules\Platform\Adapters;

use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Models\Subscription;
use App\Modules\Platform\Ports\Entitlements;

class SubscriptionEntitlements implements Entitlements
{
    public function isActive(Module $module): bool
    {
        return $this->current($module)?->isActive() ?? false;
    }

    public function quantity(Module $module): ?int
    {
        return $this->current($module)?->quantity;
    }

    public function lockQuantity(Module $module): ?int
    {
        return Subscription::query()->forModule($module)->latest('starts_on')->lockForUpdate()->first()?->quantity;
    }

    private function current(Module $module): ?Subscription
    {
        return Subscription::query()->forModule($module)->latest('starts_on')->first();
    }
}
