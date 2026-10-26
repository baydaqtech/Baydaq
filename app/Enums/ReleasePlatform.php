<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum ReleasePlatform: string
{
    use HasLabels;

    const LABELS = 'release_platform';

    case Ios = 'ios';
    case Android = 'android';
    case Web = 'web';
}
