<?php

namespace App\Modules\Invoicing\Listeners;

use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Platform\Events\PaymentSucceeded;
use App\Modules\Platform\Tenancy\CurrentTenant;

/** An invoice's payment link was paid. */
class MarkInvoicePaid
{
    public function __construct(private CurrentTenant $tenant) {}

    public function handle(PaymentSucceeded $event): void
    {
        if (! str_starts_with($event->reference, 'invoicing.invoice:') || $event->clientId === null) {
            return;
        }

        $this->tenant->runFor($event->clientId, function () use ($event): void {
            Invoice::query()->whereKey((int) substr($event->reference, strlen('invoicing.invoice:')))->whereNull('paid_at')->update(['paid_at' => now()]);
        });
    }
}
