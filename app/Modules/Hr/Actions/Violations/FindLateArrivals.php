<?php

namespace App\Modules\Hr\Actions\Violations;

use App\Modules\Hr\Models\AttendancePunch;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Models\Violation;
use App\Modules\Platform\Access\BranchSet;
use App\Modules\Platform\Ports\OrgDirectory;
use Carbon\CarbonImmutable;

/**
 * Late arrivals from the roster: the first clock-in of a rostered day later than the shift start
 * plus the grace minutes, and not yet on the disciplinary record.
 */
class FindLateArrivals
{
    public function __construct(private OrgDirectory $directory) {}

    /**
     * @return list<array{employee_id: int, date: string, starts_at: string, punched_at: string, minutes: int}>
     */
    public function handle(BranchSet $reach, string $from, string $to): array
    {
        $grace = (int) config('baydaq.hr.labor.late_grace_minutes');
        $shifts = $reach->apply(Shift::query())->whereNotNull('employee_id')->where('on_holiday', false)
            ->whereBetween('shift_date', [$from, $to])->get(['employee_id', 'company_id', 'shift_date', 'starts_at']);

        if ($shifts->isEmpty()) {
            return [];
        }

        $firstIns = AttendancePunch::query()->whereIn('employee_id', $shifts->pluck('employee_id')->unique())->where('type', 'in')
            ->whereBetween('work_date', [$from, $to])->where(fn ($query) => $query->whereNull('resolution')->orWhere('resolution', 'accepted'))
            ->orderBy('punched_at')->get(['employee_id', 'work_date', 'punched_at'])
            ->groupBy(fn (AttendancePunch $punch): string => $punch->employee_id.'|'.$punch->work_date->toDateString())->map->first();
        $recorded = Violation::query()->where('category', 'late')->where('status', '!=', 'cancelled')->whereBetween('occurred_on', [$from, $to])->get(['employee_id', 'occurred_on'])
            ->map(fn (Violation $violation): string => $violation->employee_id.'|'.$violation->occurred_on->toDateString())->flip();

        $late = [];

        foreach ($shifts as $shift) {
            $key = $shift->employee_id.'|'.$shift->shift_date->toDateString();
            $punch = $firstIns->get($key);

            if ($punch === null || $recorded->has($key)) {
                continue;
            }

            $timezone = $this->directory->company($shift->company_id)?->timezone ?? 'Asia/Riyadh';
            $start = CarbonImmutable::parse($shift->shift_date->toDateString().' '.$shift->starts_at, $timezone);
            $minutes = (int) floor($start->diffInMinutes($punch->punched_at->copy()->setTimezone($timezone), false));

            if ($minutes > $grace) {
                $late[] = ['employee_id' => $shift->employee_id, 'date' => $shift->shift_date->toDateString(), 'starts_at' => substr($shift->starts_at, 0, 5), 'punched_at' => $punch->punched_at->copy()->setTimezone($timezone)->format('H:i'), 'minutes' => $minutes];
            }
        }

        return $late;
    }
}
