<?php

namespace App\Modules\Invoicing\Actions;

use App\Modules\Invoicing\Enums\InvoiceStatus;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Platform\Ports\Messaging;
use App\Modules\Platform\Ports\Payments;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Validation\ValidationException;

/**
 * Ask a customer to pay an issued invoice online (I3): one payment link per invoice (reused
 * while it is open), sent on WhatsApp to the customer's phone. Returns the link.
 */
class SendPaymentLink
{
    public function __construct(private Payments $payments, private Messaging $messaging, private CurrentTenant $tenant) {}

    public function handle(Invoice $invoice, ?string $phone = null): string
    {
        if (! in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::Cleared, InvoiceStatus::Reported], true) || $invoice->paid_at !== null) {
            throw ValidationException::withMessages(['invoice' => __('invoicing.errors.not_payable')]);
        }

        $reference = "invoicing.invoice:{$invoice->id}";
        $link = $this->payments->openLinkFor($reference)
            ?? $this->payments->link($reference, $invoice->payableAmount(), __('invoicing.pay_description', ['number' => $invoice->number]), null, $this->tenant->id());

        $this->messaging->send($phone ?? $invoice->customer?->phone, 'inv_payment_link', [
            'name' => $invoice->customer?->localized('name') ?? '', 'number' => (string) $invoice->number, 'amount' => $invoice->payableAmount(), 'url' => $link->url,
        ], app()->getLocale(), $reference);

        return $link->url;
    }
}
