<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum RequestType: string
{
    use HasLabels;

    const LABELS = 'request_type';

    case Partnership = 'partnership';
    case Service = 'service';
}
