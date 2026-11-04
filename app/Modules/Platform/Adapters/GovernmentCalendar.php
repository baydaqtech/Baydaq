<?php

namespace App\Modules\Platform\Adapters;

use App\Modules\Platform\Data\CalendarEvent;
use App\Modules\Platform\Data\ScopeContext;
use App\Modules\Platform\Ports\CalendarSource;
use App\Modules\Platform\Ports\Entitlements;
use Carbon\CarbonImmutable;

/**
 * Recurring government deadlines: GOSI contributions by the 15th of each month (for people who
 * see payroll) and the quarterly VAT return by the end of the month after the quarter (for people
 * who see invoices or finance reports).
 */
class GovernmentCalendar implements CalendarSource
{
    public function __construct(private Entitlements $entitlements) {}

    public function events(CarbonImmutable $from, CarbonImmutable $to, ScopeContext $context): array
    {
        if (! $this->entitlements->hasFeature('suite.gov')) {
            return [];
        }

        $gosi = ! $context->reach('hr.payroll.view')->isEmpty();
        $vat = ! $context->reach('invoicing.invoices.view')->isEmpty() || ! $context->reach('finance.reports.view')->isEmpty();
        $events = [];

        for ($month = $from->startOfMonth(); $month->lte($to); $month = $month->addMonth()) {
            $gosiDay = $month->day(15);

            if ($gosi && $gosiDay->between($from, $to)) {
                $events[] = new CalendarEvent("gov.gosi:{$month->format('Y-m')}", 'government', __('suite.calendar.gosi_due'), $gosiDay->toDateString(), url: '/gov', tone: 'warn');
            }

            // VAT for the quarter that ended last month is due at the end of this month.
            if ($vat && in_array($month->month, [1, 4, 7, 10], true) && $month->endOfMonth()->between($from, $to)) {
                $events[] = new CalendarEvent("gov.vat:{$month->format('Y-m')}", 'government', __('suite.calendar.vat_due'), $month->endOfMonth()->toDateString(), url: '/gov', tone: 'warn');
            }
        }

        return $events;
    }
}
