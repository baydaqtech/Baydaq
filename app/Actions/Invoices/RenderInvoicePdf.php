<?php

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Models\Setting;
use App\Modules\Platform\Adapters\MpdfRenderer;
use Mpdf\Output\Destination;

class RenderInvoicePdf
{
    /**
     * The invoice as a bilingual (Arabic first) A4 PDF, in the Baydaq brand.
     *
     * mPDF is used because it shapes Arabic (joined letters, right to left); MpdfRenderer
     * holds its font setup.
     */
    public function handle(Invoice $invoice): string
    {
        $invoice->loadMissing(['client', 'project', 'items']);

        $html = view('pdf.invoice', [
            'invoice' => $invoice,
            'issuer' => $invoice->issuer ?? Setting::issuer(),
            'logo' => public_path('brand/logo-horizontal.png'),
        ])->render();

        $pdf = MpdfRenderer::make();
        $pdf->SetTitle($invoice->number);
        $pdf->WriteHTML($html);

        return $pdf->Output('', Destination::STRING_RETURN);
    }
}
