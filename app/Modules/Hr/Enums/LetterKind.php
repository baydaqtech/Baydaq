<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum LetterKind: string
{
    use HasLabels;

    const LABELS = 'letter_kind';

    case Salary = 'salary';
    case Experience = 'experience';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
