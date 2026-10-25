<?php

namespace App\Modules\Finance\Adapters;

use App\Modules\Finance\Models\Bill;
use App\Modules\Platform\Data\CalendarEvent;
use App\Modules\Platform\Data\ScopeContext;
use App\Modules\Platform\Ports\CalendarSource;
use App\Modules\Platform\Ports\Entitlements;
use Carbon\CarbonImmutable;

/**
 * Supplier bills falling due, for people who see payables in those branches.
 */
class BillsCalendar implements CalendarSource
{
    public function __construct(private Entitlements $entitlements) {}

    public function events(CarbonImmutable $from, CarbonImmutable $to, ScopeContext $context): array
    {
        $reach = $context->reach('finance.payables.view');

        if (! $this->entitlements->hasFeature('finance.purchasing') || $reach->isEmpty()) {
            return [];
        }

        $bills = $reach->apply(Bill::query()->with('supplier')->where('status', 'approved')->whereBetween('due_on', [$from->toDateString(), $to->toDateString()]))->get();

        return $bills->map(fn (Bill $bill): CalendarEvent => new CalendarEvent(
            "finance.bill:{$bill->id}", 'bill',
            __('suite.calendar.bill_due', ['supplier' => $bill->supplier?->name, 'amount' => number_format((float) $bill->total - (float) $bill->paid, 2)]),
            $bill->due_on->toDateString(), url: '/finance/purchasing', tone: $bill->due_on->isPast() ? 'bad' : 'warn',
        ))->all();
    }
}
