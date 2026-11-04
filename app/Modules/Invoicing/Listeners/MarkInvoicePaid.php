<?php

namespace App\Modules\Invoicing\Listeners;

use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Platform\Data\LedgerPosting;
use App\Modules\Platform\Events\PaymentSucceeded;
use App\Modules\Platform\Ports\Ledger;
use App\Modules\Platform\Tenancy\CurrentTenant;

/** An invoice's payment link was paid. */
class MarkInvoicePaid
{
    public function __construct(private CurrentTenant $tenant, private Ledger $ledger) {}

    public function handle(PaymentSucceeded $event): void
    {
        if (! str_starts_with($event->reference, 'invoicing.invoice:') || $event->clientId === null) {
            return;
        }

        $this->tenant->runFor($event->clientId, function () use ($event): void {
            $invoice = Invoice::query()->whereKey((int) substr($event->reference, strlen('invoicing.invoice:')))->whereNull('paid_at')->first();

            if ($invoice === null) {
                return;
            }

            $invoice->forceFill(['paid_at' => now()])->save();
            $this->ledger->post(new LedgerPosting($invoice->company_id, null, today()->toDateString(), 'invoicing.payment', $invoice->id, "{$invoice->number}", [
                ['account' => 'bank', 'debit' => $invoice->payableAmount()],
                ['account' => 'receivables', 'credit' => $invoice->payableAmount()],
            ]));
        });
    }
}
