<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum ProjectType: string
{
    use HasLabels;

    const LABELS = 'project_type';

    case PoweredByBaydaq = 'powered_by_baydaq';
    case ClientOwned = 'client_owned';
}
