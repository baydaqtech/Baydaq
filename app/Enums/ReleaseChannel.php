<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum ReleaseChannel: string
{
    use HasLabels;

    const LABELS = 'release_channel';

    case TestFlight = 'testflight';
    case PlayInternal = 'play_internal';
    case Staging = 'staging';
    case Store = 'store';
    case Production = 'production';
}
