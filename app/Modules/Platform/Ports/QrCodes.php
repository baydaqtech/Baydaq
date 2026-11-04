<?php

namespace App\Modules\Platform\Ports;

/**
 * QR codes for cards, verification links and door access, drawn on the server.
 */
interface QrCodes
{
    /** An SVG data URI (data:image/svg+xml;base64,…) for an <img> tag or a PDF. */
    public function svgDataUri(string $content): string;
}
