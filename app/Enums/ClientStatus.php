<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum ClientStatus: string
{
    use HasLabels;

    const LABELS = 'client_status';

    case Active = 'active';
    case Paused = 'paused';
}
