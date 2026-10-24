<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum Gender: string
{
    use HasLabels;

    const LABELS = 'gender';

    case Male = 'male';
    case Female = 'female';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
