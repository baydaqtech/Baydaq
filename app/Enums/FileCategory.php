<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum FileCategory: string
{
    use HasLabels;

    const LABELS = 'file_category';

    case Build = 'build';
    case Design = 'design';
    case Doc = 'doc';
    case Other = 'other';
}
