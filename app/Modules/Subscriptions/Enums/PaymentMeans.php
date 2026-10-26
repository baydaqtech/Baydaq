<?php

namespace App\Modules\Subscriptions\Enums;

use App\Enums\Concerns\HasLabels;

enum PaymentMeans: string
{
    use HasLabels;

    const LABELS = 'payment_means';

    case Cash = '10';
    case Card = '48';
    case Transfer = '42';

    protected static function labelFile(): string
    {
        return 'subscriptions';
    }
}
