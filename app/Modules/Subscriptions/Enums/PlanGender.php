<?php

namespace App\Modules\Subscriptions\Enums;

use App\Enums\Concerns\HasLabels;

enum PlanGender: string
{
    use HasLabels;

    const LABELS = 'plan_gender';

    case Any = 'any';
    case Male = 'male';
    case Female = 'female';

    protected static function labelFile(): string
    {
        return 'subscriptions';
    }
}
