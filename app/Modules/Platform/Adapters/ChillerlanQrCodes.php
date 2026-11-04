<?php

namespace App\Modules\Platform\Adapters;

use App\Modules\Platform\Ports\QrCodes;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class ChillerlanQrCodes implements QrCodes
{
    public function svgDataUri(string $content): string
    {
        return (new QRCode(new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => true,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
        ])))->render($content);
    }
}
