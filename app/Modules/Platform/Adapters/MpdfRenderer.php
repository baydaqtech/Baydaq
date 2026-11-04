<?php

namespace App\Modules\Platform\Adapters;

use App\Modules\Platform\Ports\PdfRenderer;
use Illuminate\Support\Facades\File;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * mPDF with IBM Plex Sans Arabic (static TTFs in resources/fonts). mPDF cannot shape Alexandria,
 * the site's font, because of the mark glyph sets in its GDEF table.
 */
class MpdfRenderer implements PdfRenderer
{
    public function render(string $view, array $data, string $title): string
    {
        $pdf = self::make();
        $pdf->SetTitle($title);
        $pdf->WriteHTML(view($view, $data)->render());

        return $pdf->Output('', Destination::STRING_RETURN);
    }

    public static function make(): Mpdf
    {
        $tempDir = storage_path('app/mpdf');
        File::ensureDirectoryExists($tempDir);

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'fontDir' => [...(new ConfigVariables)->getDefaults()['fontDir'], resource_path('fonts')],
            'fontdata' => (new FontVariables)->getDefaults()['fontdata'] + [
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
