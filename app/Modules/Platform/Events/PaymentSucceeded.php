<?php

namespace App\Modules\Platform\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A payment link was paid. The module owning the reference marks its record paid.
 */
final class PaymentSucceeded
{
    use Dispatchable;

    public function __construct(
        public readonly int $paymentId,
        public readonly string $reference,
        public readonly string $amount,
        public readonly ?int $clientId,
    ) {}
}
