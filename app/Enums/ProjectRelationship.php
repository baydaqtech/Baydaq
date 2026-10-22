<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum ProjectRelationship: string
{
    use HasLabels;

    const LABELS = 'project_relationship';

    case Partnership = 'partnership';
    case Product = 'product';
}
