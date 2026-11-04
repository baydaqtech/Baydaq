<?php

namespace App\Modules\Platform\Data;

/**
 * One entry on the calendar. $title is already translated; $category groups entries for the
 * filter (leave, holiday, training...); $url is a suite path relative to the scope, or null.
 */
final readonly class CalendarEvent
{
    /**
     * @param  'info'|'good'|'warn'|'bad'  $tone
     */
    public function __construct(
        public string $key,
        public string $category,
        public string $title,
        public string $date,
        public ?string $endDate = null,
        public ?string $url = null,
        public string $tone = 'info',
    ) {}
}
