<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Data\CalendarEvent;
use App\Modules\Platform\Data\ScopeContext;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\CalendarSource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * One calendar for everything with a date: every module contributes its events through the
 * CalendarSource port, each limited to what the user may see.
 */
class CalendarController extends Controller
{
    use AuthorizesSuite, RendersSuitePages;

    public function __invoke(Request $request): Response
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? CarbonImmutable::createFromFormat('!Y-m', $request->query('month')) : CarbonImmutable::today('Asia/Riyadh')->startOfMonth();
        $from = $month->startOfMonth()->startOfWeek(CarbonImmutable::SUNDAY);
        $to = $month->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY);

        $events = [];

        foreach (app()->tagged(CalendarSource::TAG) as $source) {
            array_push($events, ...$source->events($from, $to, $this->context()));
        }

        usort($events, fn (CalendarEvent $a, CalendarEvent $b): int => [$a->date, $a->category, $a->title] <=> [$b->date, $b->category, $b->title]);

        return $this->suitePage('Suite/Calendar', [
            'month' => $month->format('Y-m'),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'today' => CarbonImmutable::today('Asia/Riyadh')->toDateString(),
            'events' => array_map(fn (CalendarEvent $event): array => (array) $event, $events),
            'categories' => array_values(array_unique(array_map(fn (CalendarEvent $event): string => $event->category, $events))),
        ]);
    }

    private function context(): ScopeContext
    {
        $access = $this->access();
        $scope = $this->scope();

        return new ScopeContext(fn (string $key) => $scope->branchesFor($key, $access), $scope->branchCompanies);
    }
}
