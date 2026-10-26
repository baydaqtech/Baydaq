<?php

namespace App\Modules\Hr\Actions\Breaks;

use App\Modules\Hr\Enums\BreakKind;
use App\Modules\Hr\Enums\BreakStatus;
use App\Modules\Hr\Enums\PunchResolution;
use App\Modules\Hr\Enums\PunchType;
use App\Modules\Hr\Models\AttendancePunch;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Models\WorkBreak;
use App\Modules\Hr\Support\HrSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Work breaks. An employee who is clocked in takes a break from the app (straight away, or by
 * asking when the tenant wants a manager to agree); a manager can give anyone a break, approve
 * or refuse a request, and end a break. A branch can cap how many are on break at once.
 */
class BreakFlow
{
    public function __construct(private HrSettings $settings) {}

    /** The employee starts their break, or asks for one when breaks need a manager's agreement. */
    public function take(Employee $employee, ?int $minutes, ?string $note, Authenticatable $actor): WorkBreak
    {
        $this->ensureCanBreak($employee);
        $shift = $this->shiftToday($employee);
        $minutes = $this->minutes($minutes ?? $shift?->break_minutes);
        $kind = $shift?->break_at !== null && $this->nearScheduled($employee, $shift) ? BreakKind::Scheduled : BreakKind::Requested;
        $selfStart = $kind === BreakKind::Scheduled || (bool) $this->settings->get('break_self_start');

        return DB::transaction(function () use ($employee, $minutes, $note, $kind, $selfStart): WorkBreak {
            $start = $selfStart && $this->hasRoom($employee->branch_id);

            return $this->create($employee, $kind, $start ? BreakStatus::OnBreak : BreakStatus::Requested, $minutes, $note, null);
        });
    }

    /** A manager gives an employee a break now ("you can go for your break"). */
    public function grant(Employee $employee, ?int $minutes, Authenticatable $actor): WorkBreak
    {
        $this->ensureCanBreak($employee);

        return $this->create($employee, BreakKind::Granted, BreakStatus::OnBreak, $this->minutes($minutes), null, $actor);
    }

    public function decide(WorkBreak $break, bool $approve, Authenticatable $actor): WorkBreak
    {
        if ($break->status !== BreakStatus::Requested) {
            throw ValidationException::withMessages(['status' => __('hr.breaks.errors.not_requested')]);
        }

        if ($approve && ! $this->hasRoom($break->branch_id)) {
            throw ValidationException::withMessages(['status' => __('hr.breaks.errors.full')]);
        }

        $break->forceFill([
            'status' => $approve ? BreakStatus::OnBreak : BreakStatus::Rejected,
            'starts_at' => $approve ? now() : null,
            'decided_by' => $actor->getAuthIdentifier(),
        ])->save();

        return $break;
    }

    /** Back from break (the employee, or a manager on their behalf). A pending request is withdrawn. */
    public function end(WorkBreak $break): WorkBreak
    {
        match ($break->status) {
            BreakStatus::OnBreak => $break->forceFill(['status' => BreakStatus::Ended, 'ends_at' => now()])->save(),
            BreakStatus::Requested => $break->forceFill(['status' => BreakStatus::Cancelled])->save(),
            default => throw ValidationException::withMessages(['status' => __('hr.breaks.errors.not_on_break')]),
        };

        return $break;
    }

    /**
     * Who on this branch has worked 5 hours or more since clocking in without a break today:
     * Labor Law Art. 101 asks for a rest of at least 30 minutes after at most 5 hours of work.
     *
     * @param  list<int>  $employeeIds  employees clocked in now
     * @return array<int, int> employee id => minutes worked without a break
     */
    public function dueForBreak(array $employeeIds): array
    {
        $today = CarbonImmutable::now('Asia/Riyadh')->toDateString();
        $firstIn = AttendancePunch::query()->whereIn('employee_id', $employeeIds)->whereDate('work_date', $today)->where('type', PunchType::In)
            ->selectRaw('employee_id, min(punched_at) as first_in')->groupBy('employee_id')->pluck('first_in', 'employee_id');
        $lastBreak = WorkBreak::query()->whereIn('employee_id', $employeeIds)->whereDate('work_date', $today)->whereIn('status', [BreakStatus::OnBreak, BreakStatus::Ended])
            ->selectRaw('employee_id, max(coalesce(ends_at, starts_at)) as last_break')->groupBy('employee_id')->pluck('last_break', 'employee_id');

        $due = [];

        foreach ($firstIn as $employeeId => $since) {
            $from = CarbonImmutable::parse($lastBreak[$employeeId] ?? $since);
            $worked = (int) $from->diffInMinutes(now());

            if ($worked >= 300) {
                $due[(int) $employeeId] = $worked;
            }
        }

        return $due;
    }

    /** Employees clocked in right now at these branches (their last punch today is "in"). */
    public function clockedIn(array $branchIds): array
    {
        $today = CarbonImmutable::now('Asia/Riyadh')->toDateString();

        return AttendancePunch::query()->whereIn('home_branch_id', $branchIds)->whereDate('work_date', $today)
            ->where(fn ($query) => $query->whereNull('resolution')->orWhere('resolution', '!=', PunchResolution::Rejected->value))
            ->orderBy('punched_at')->get(['employee_id', 'type'])
            ->groupBy('employee_id')->filter(fn ($punches) => $punches->last()->type === PunchType::In)->keys()->map(fn ($id) => (int) $id)->values()->all();
    }

    public function active(Employee $employee): ?WorkBreak
    {
        return WorkBreak::query()->where('employee_id', $employee->id)->whereIn('status', [BreakStatus::Requested, BreakStatus::OnBreak])->latest('id')->first();
    }

    public function shiftToday(Employee $employee): ?Shift
    {
        return Shift::query()->where('employee_id', $employee->id)->whereDate('shift_date', CarbonImmutable::now('Asia/Riyadh')->toDateString())->orderBy('starts_at')->first();
    }

    private function create(Employee $employee, BreakKind $kind, BreakStatus $status, int $minutes, ?string $note, ?Authenticatable $actor): WorkBreak
    {
        $break = (new WorkBreak)->forceFill([
            'company_id' => $employee->company_id, 'branch_id' => $employee->branch_id, 'employee_id' => $employee->id,
            'kind' => $kind, 'status' => $status, 'minutes' => $minutes, 'work_date' => CarbonImmutable::now('Asia/Riyadh')->toDateString(),
            'requested_at' => now(), 'starts_at' => $status === BreakStatus::OnBreak ? now() : null,
            'decided_by' => $actor?->getAuthIdentifier(), 'note' => $note,
        ]);
        $break->save();

        return $break;
    }

    private function ensureCanBreak(Employee $employee): void
    {
        if (! in_array($employee->id, $this->clockedIn([$employee->branch_id]), true)) {
            throw ValidationException::withMessages(['employee' => __('hr.breaks.errors.not_clocked_in')]);
        }

        if ($this->active($employee) !== null) {
            throw ValidationException::withMessages(['employee' => __('hr.breaks.errors.already')]);
        }
    }

    private function hasRoom(int $branchId): bool
    {
        $limit = (int) $this->settings->get('break_max_concurrent');

        return $limit === 0 || WorkBreak::query()->where('branch_id', $branchId)->where('status', BreakStatus::OnBreak)->lockForUpdate()->count() < $limit;
    }

    private function minutes(?int $minutes): int
    {
        return max(5, min(120, $minutes ?: (int) $this->settings->get('break_minutes')));
    }

    /** Within 30 minutes either side of the break time on today's shift. */
    private function nearScheduled(Employee $employee, Shift $shift): bool
    {
        $at = CarbonImmutable::parse(CarbonImmutable::now('Asia/Riyadh')->toDateString().' '.$shift->break_at, 'Asia/Riyadh');

        return abs(CarbonImmutable::now('Asia/Riyadh')->diffInMinutes($at, false)) <= 30;
    }
}
