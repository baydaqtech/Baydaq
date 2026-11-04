<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum ProjectServiceStatus: string
{
    use HasLabels;

    const LABELS = 'project_service_status';

    case Active = 'active';
    case Paused = 'paused';
}
