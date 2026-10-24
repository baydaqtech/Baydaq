<?php

namespace App\Modules\Platform\Ports;

use App\Modules\Platform\Enums\Module;

/**
 * What the current tenant has bought. Modules ask here before letting a seat, register or
 * branch be used; they never read the subscriptions table.
 */
interface Entitlements
{
    public function isActive(Module $module): bool;

    /** The quantity cap (seats, registers...), or null when the module is not subscribed. */
    public function quantity(Module $module): ?int;

    /**
     * Lock the subscription row and return its cap, so two activations can't both pass the
     * same last seat. Call inside a transaction.
     */
    public function lockQuantity(Module $module): ?int;
}
