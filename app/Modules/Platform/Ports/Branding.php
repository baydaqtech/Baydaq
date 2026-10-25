<?php

namespace App\Modules\Platform\Ports;

/**
 * The client's own look (colours, logo, name, roundness, light or dark), for /me and the
 * employee app. Null means Baydaq's own look.
 */
interface Branding
{
    /** @return array{app_name: ?string, logo_url: ?string, primary: ?string, primary_foreground: ?string, accent: ?string, radius: string, mode: string}|null */
    public function theme(): ?array;
}
