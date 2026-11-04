<?php

namespace App\Http\Controllers;

use App\Actions\Invoices\RenderInvoicePdf;
use App\Models\Invoice;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class InvoicePdfController extends Controller
{
    /**
     * Download an invoice as PDF (admin and client portal; the policy decides who may).
     */
    public function __invoke(Invoice $invoice, RenderInvoicePdf $renderInvoicePdf): Response
    {
        Gate::authorize('view', $invoice);

        return response($renderInvoicePdf->handle($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$invoice->number.'.pdf"',
        ]);
    }
}
