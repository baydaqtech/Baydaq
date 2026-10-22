<?php

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Support\Facades\File;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class RenderInvoicePdf
{
    /**
     * The invoice as a bilingual (Arabic first) A4 PDF, in the Baydaq brand.
     *
     * mPDF is used because it shapes Arabic (joined letters, right to left). The font is
     * IBM Plex Sans Arabic (static TTFs in resources/fonts): mPDF cannot shape Alexandria,
     * the site's font, because of the mark glyph sets in its GDEF table.
     */
    public function handle(Invoice $invoice): string
    {
        $invoice->loadMissing(['client', 'project', 'items']);

        $html = view('pdf.invoice', [
            'invoice' => $invoice,
            'issuer' => $invoice->issuer ?? Setting::issuer(),
            'logo' => public_path('brand/logo-horizontal.png'),
        ])->render();

        $pdf = $this->mpdf();
        $pdf->SetTitle($invoice->number);
        $pdf->WriteHTML($html);

        return $pdf->Output('', Destination::STRING_RETURN);
    }

    private function mpdf(): Mpdf
    {
        $tempDir = storage_path('app/mpdf');
        File::ensureDirectoryExists($tempDir);

        $fontDirs = (new ConfigVariables)->getDefaults()['fontDir'];
        $fontData = (new FontVariables)->getDefaults()['fontdata'];

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'fontDir' => [...$fontDirs, resource_path('fonts')],
            'fontdata' => $fontData + [
                'plexarabic' => [
                    'R' => 'IBMPlexSansArabic-Regular.ttf',
                    'B' => 'IBMPlexSansArabic-Bold.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
            ],
            'default_font' => 'plexarabic',
            'directionality' => 'rtl',
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_top' => 14,
            'margin_bottom' => 16,
        ]);
    }
}
