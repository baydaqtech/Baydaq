<?php

namespace App\Modules\Platform\Adapters;

use App\Modules\Platform\Models\Theme;
use App\Modules\Platform\Ports\Branding;

class ThemeBranding implements Branding
{
    public function theme(): ?array
    {
        return Theme::payload();
    }
}
