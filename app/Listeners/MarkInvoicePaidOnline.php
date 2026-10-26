<?php

namespace App\Listeners;

use App\Actions\Invoices\ChangeInvoiceStatus;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Modules\Platform\Events\PaymentSucceeded;

/** A Baydaq invoice paid online from the portal is marked paid. */
class MarkInvoicePaidOnline
{
    public function __construct(private ChangeInvoiceStatus $changeStatus) {}

    public function handle(PaymentSucceeded $event): void
    {
        if (! str_starts_with($event->reference, 'core.invoice:')) {
            return;
        }

        $invoice = Invoice::query()->find((int) substr($event->reference, strlen('core.invoice:')));

        if ($invoice && in_array($invoice->status, [InvoiceStatus::Sent, InvoiceStatus::Overdue], true)) {
            $this->changeStatus->handle($invoice, InvoiceStatus::Paid);
        }
    }
}
