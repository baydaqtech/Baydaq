<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum PricingModel: string
{
    use HasLabels;

    const LABELS = 'pricing_model';

    case Monthly = 'monthly';
    case PerUse = 'per_use';
}
