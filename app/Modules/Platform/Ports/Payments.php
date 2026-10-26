<?php

namespace App\Modules\Platform\Ports;

use App\Modules\Platform\Data\PaymentLink;

/**
 * Online payment through a hosted payment page (mada, Apple Pay, cards). The module that owns
 * the payable thing creates a link with a reference like "invoicing.invoice:34" and listens to
 * Events\PaymentSucceeded for that reference.
 */
interface Payments
{
    public function link(string $reference, string $amount, string $description, ?string $returnUrl = null, ?int $clientId = null): PaymentLink;

    /** The latest link for a reference that can still be paid, if any. */
    public function openLinkFor(string $reference): ?PaymentLink;
}
