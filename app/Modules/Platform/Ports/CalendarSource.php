<?php

namespace App\Modules\Platform\Ports;

use App\Modules\Platform\Data\CalendarEvent;
use App\Modules\Platform\Data\ScopeContext;
use Carbon\CarbonImmutable;

/**
 * Modules put their dates on the shared calendar by tagging an implementation 'calendar.sources'.
 * The calendar stores nothing itself; each source reports only what the user's permissions reach.
 */
interface CalendarSource
{
    public const TAG = 'calendar.sources';

    /**
     * @return list<CalendarEvent>
     */
    public function events(CarbonImmutable $from, CarbonImmutable $to, ScopeContext $context): array;
}
