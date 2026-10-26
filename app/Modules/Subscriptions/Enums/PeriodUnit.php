<?php

namespace App\Modules\Subscriptions\Enums;

use App\Enums\Concerns\HasLabels;
use Carbon\CarbonImmutable;

enum PeriodUnit: string
{
    use HasLabels;

    const LABELS = 'period_unit';

    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    /** The last day of a period of `count` units starting on `start` (a month from 15 Jan ends 14 Feb). */
    public function lastDay(CarbonImmutable $start, int $count): CarbonImmutable
    {
        $next = match ($this) {
            self::Day => $start->addDays($count),
            self::Week => $start->addWeeks($count),
            self::Month => $start->addMonthsNoOverflow($count),
            self::Year => $start->addYearsNoOverflow($count),
        };

        return $next->subDay();
    }

    protected static function labelFile(): string
    {
        return 'subscriptions';
    }
}
