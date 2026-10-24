<?php

namespace App\Modules\Invoicing\Actions;

use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Support\Facades\File;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class RenderInvoicePdf
{
    public function __construct(private OrgDirectory $directory) {}

    /**
     * The bilingual A4 PDF of a tenant invoice, with the ZATCA QR once it has been issued.
     * Same mPDF setup as the Baydaq invoices (IBM Plex Sans Arabic shapes Arabic correctly).
     */
    public function handle(Invoice $invoice): string
    {
        $invoice->loadMissing(['lines', 'customer', 'related']);

        $html = view('invoicing::pdf', [
            'invoice' => $invoice,
            'company' => $this->directory->company($invoice->company_id),
        ])->render();

        $pdf = $this->mpdf();
        $pdf->SetTitle($invoice->number ?? $invoice->uuid);
        $pdf->WriteHTML($html);

        return $pdf->Output('', Destination::STRING_RETURN);
    }

    private function mpdf(): Mpdf
    {
        $tempDir = storage_path('app/mpdf');
        File::ensureDirectoryExists($tempDir);

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'fontDir' => [...(new ConfigVariables)->getDefaults()['fontDir'], resource_path('fonts')],
            'fontdata' => (new FontVariables)->getDefaults()['fontdata'] + [
                'plexarabic' => ['R' => 'IBMPlexSansArabic-Regular.ttf', 'B' => 'IBMPlexSansArabic-Bold.ttf', 'useOTL' => 0xFF, 'useKashida' => 75],
            ],
            'default_font' => 'plexarabic',
            'directionality' => 'rtl',
            'margin_left' => 14, 'margin_right' => 14, 'margin_top' => 14, 'margin_bottom' => 16,
        ]);
    }
}
