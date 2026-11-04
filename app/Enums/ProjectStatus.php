<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum ProjectStatus: string
{
    use HasLabels;

    const LABELS = 'project_status';

    case Planning = 'planning';
    case InDevelopment = 'in_development';
    case Live = 'live';
    case Paused = 'paused';
}
