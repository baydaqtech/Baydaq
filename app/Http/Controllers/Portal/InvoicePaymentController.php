<?php

namespace App\Http\Controllers\Portal;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Modules\Platform\Ports\Payments;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pay a Baydaq invoice online from the client portal (B4). Sends the client to the hosted
 * payment page; the invoice is marked paid when the payment arrives.
 */
class InvoicePaymentController extends Controller
{
    public function __invoke(Invoice $invoice, Payments $payments): Response
    {
        Gate::authorize('view', $invoice);
        abort_unless(in_array($invoice->status, [InvoiceStatus::Sent, InvoiceStatus::Overdue], true), 422, __('common.payments.status.paid'));

        $reference = "core.invoice:{$invoice->id}";
        $link = $payments->openLinkFor($reference)
            ?? $payments->link($reference, (string) $invoice->total, __('portal.statement.pay_description', ['number' => $invoice->number]), route('portal.statement'));

        return Inertia::location($link->url);
    }
}
