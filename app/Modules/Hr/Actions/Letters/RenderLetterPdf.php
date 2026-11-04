<?php

namespace App\Modules\Hr\Actions\Letters;

use App\Modules\Hr\Models\Letter;
use App\Modules\Platform\Ports\PdfRenderer;
use App\Modules\Platform\Ports\QrCodes;

/**
 * The letter as an A4 PDF in its language, with a QR code that opens the verification page.
 */
class RenderLetterPdf
{
    public function __construct(private PdfRenderer $pdf, private QrCodes $qr) {}

    public function handle(Letter $letter): string
    {
        $url = route('hr.letters.verify', $letter->token);

        return $this->pdf->render('pdf.hr-letter', [
            'letter' => $letter,
            'snapshot' => $letter->snapshot,
            'locale' => $letter->language,
            'qr' => $this->qr->svgDataUri($url),
            'verifyUrl' => $url,
        ], $letter->reference);
    }
}
