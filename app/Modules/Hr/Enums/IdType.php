<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum IdType: string
{
    use HasLabels;

    const LABELS = 'id_type';

    case NationalId = 'national_id';
    case Iqama = 'iqama';
    case Passport = 'passport';
    case GccId = 'gcc_id';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}
