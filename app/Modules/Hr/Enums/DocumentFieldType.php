<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum DocumentFieldType: string
{
    use HasLabels;

    const LABELS = 'document_field_type';

    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case Boolean = 'boolean';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
