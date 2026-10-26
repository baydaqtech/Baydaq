<?php

namespace App\Modules\Hr\Actions\Offboarding;

use App\Modules\Hr\Actions\Employees\ChangeEmployeeStatus;
use App\Modules\Hr\Actions\Payroll\Gosi;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\FinalSettlement;
use App\Modules\Hr\Models\Separation;
use App\Modules\Hr\Ports\OffboardingClearance;
use App\Modules\Platform\Ports\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Leaving the company, following the Labor Law as amended in February 2025: a resignation the
 * employer does not answer is accepted after 30 days, the employer may postpone it once (up to 60
 * days from submission) when the work needs it, and the employee may withdraw within 7 days.
 * Completing the separation terminates the employee, which still waits for the asset clearance.
 */
class SeparationFlow
{
    public function __construct(private ChangeEmployeeStatus $changeStatus, private OffboardingClearance $clearance, private Audit $audit) {}

    /**
     * @param  array{kind: string, last_day?: ?string, reason?: ?string, submitted_on?: ?string}  $data
     */
    public function submit(Employee $employee, array $data, string $source, ?Authenticatable $actor = null): Separation
    {
        // One departure at a time per employee: lock the employee so two submissions can't both pass.
        return DB::transaction(function () use ($employee, $data, $source, $actor): Separation {
            Employee::query()->lockForUpdate()->whereKey($employee->id)->value('id');

            return $this->submitLocked($employee, $data, $source, $actor);
        });
    }

    /**
     * @param  array{kind: string, last_day?: ?string, reason?: ?string, submitted_on?: ?string}  $data
     */
    private function submitLocked(Employee $employee, array $data, string $source, ?Authenticatable $actor): Separation
    {
        if ($employee->status === EmployeeStatus::Terminated) {
            throw ValidationException::withMessages(['kind' => __('hr.separations.errors.already_left')]);
        }

        if (Separation::query()->where('employee_id', $employee->id)->whereIn('status', Separation::OPEN)->exists()) {
            throw ValidationException::withMessages(['kind' => __('hr.separations.errors.open')]);
        }

        $labor = config('baydaq.hr.labor');
        $submittedOn = CarbonImmutable::parse($data['submitted_on'] ?? today());
        $resignation = $data['kind'] === 'resignation';
        $notice = $resignation ? $labor['notice_days_employee'] : ($data['kind'] === 'termination' ? $labor['notice_days_employer'] : 0);
        $lastDay = isset($data['last_day']) ? CarbonImmutable::parse($data['last_day']) : $submittedOn->addDays($notice);

        if ($lastDay->lt($submittedOn)) {
            throw ValidationException::withMessages(['last_day' => __('hr.separations.errors.last_day')]);
        }

        $separation = (new Separation)->forceFill([
            'employee_id' => $employee->id,
            'kind' => $data['kind'],
            'status' => $resignation ? 'submitted' : 'accepted',
            'source' => $source,
            'submitted_on' => $submittedOn->toDateString(),
            'last_day' => $lastDay->toDateString(),
            'deemed_accept_on' => $resignation ? $submittedOn->addDays($labor['resignation_deemed_days'])->toDateString() : null,
            'reason' => $data['reason'] ?? null,
            'checklist' => [],
            'submitted_by' => $actor?->getAuthIdentifier(),
            'decided_by' => $resignation ? null : $actor?->getAuthIdentifier(),
            'decided_at' => $resignation ? null : now(),
        ]);
        $separation->save();
        $this->audit->record($separation, 'submitted', ['kind' => $separation->kind, 'last_day' => $separation->last_day->toDateString()], $actor);

        return $separation;
    }

    /** The employee takes a resignation back within the withdrawal window. */
    public function withdraw(Separation $separation, ?Authenticatable $actor = null): Separation
    {
        if (! self::canWithdraw($separation)) {
            throw ValidationException::withMessages(['action' => __('hr.separations.errors.withdraw')]);
        }

        return $this->move($separation, 'withdrawn', [], $actor);
    }

    public function accept(Separation $separation, ?string $lastDay = null, ?Authenticatable $actor = null): Separation
    {
        $this->expect($separation, ['submitted', 'deferred']);

        return $this->move($separation, 'accepted', array_filter(['last_day' => $lastDay]), $actor);
    }

    /**
     * Postpone a resignation for the needs of the work, no further than the legal maximum after
     * submission. The new date becomes both the last day and the deemed-acceptance date.
     */
    public function defer(Separation $separation, string $to, ?Authenticatable $actor = null): Separation
    {
        $this->expect($separation, ['submitted']);
        $to = CarbonImmutable::parse($to);
        $max = CarbonImmutable::parse($separation->submitted_on)->addDays(config('baydaq.hr.labor.resignation_max_defer_days'));

        if ($separation->kind !== 'resignation' || $to->gt($max) || $to->lte($separation->last_day)) {
            throw ValidationException::withMessages(['last_day' => __('hr.separations.errors.defer', ['max' => $max->toDateString()])]);
        }

        return $this->move($separation, 'deferred', ['last_day' => $to->toDateString(), 'deemed_accept_on' => $to->toDateString()], $actor);
    }

    public function cancel(Separation $separation, ?Authenticatable $actor = null): Separation
    {
        $this->expect($separation, Separation::OPEN);

        return $this->move($separation, 'cancelled', [], $actor);
    }

    /** Tick or untick an item of the exit checklist. */
    public function check(Separation $separation, string $item, bool $done, ?Authenticatable $actor = null): Separation
    {
        abort_unless(in_array($item, Separation::CHECKLIST, true) && $item !== 'assets', 422);
        $separation->checklist = [...($separation->checklist ?? []), $item => $done ? now()->toDateString() : null];
        $separation->save();

        return $separation;
    }

    /**
     * End the employment on the last day. The modules that lent the employee something must have
     * cleared them first (ChangeEmployeeStatus refuses otherwise).
     */
    public function complete(Separation $separation, ?Authenticatable $actor = null): Separation
    {
        $this->expect($separation, ['accepted']);

        return DB::transaction(function () use ($separation, $actor): Separation {
            if ($separation->employee->status !== EmployeeStatus::Terminated) {
                $this->changeStatus->handle($separation->employee, EmployeeStatus::Terminated, $actor, $separation->last_day->toDateString());
            }

            return $this->move($separation, 'completed', [], $actor);
        });
    }

    /**
     * Resignations nobody answered in time count as accepted. Run daily; returns how many moved.
     */
    public function acceptOverdue(): int
    {
        $due = Separation::query()->whereIn('status', ['submitted', 'deferred'])->whereDate('deemed_accept_on', '<=', today())->get();

        foreach ($due as $separation) {
            $separation->forceFill(['status' => 'accepted', 'deemed' => true, 'decided_at' => now()])->save();
            $this->audit->record($separation, 'deemed_accepted', []);
        }

        return $due->count();
    }

    public static function canWithdraw(Separation $separation): bool
    {
        return $separation->kind === 'resignation'
            && in_array($separation->status, ['submitted', 'deferred'], true)
            && today()->lte($separation->submitted_on->copy()->addDays(config('baydaq.hr.labor.resignation_withdraw_days')));
    }

    /**
     * The checklist as shown: what was ticked, plus what the system knows (assets cleared,
     * settlement recorded); `muqeem` only for non-Saudis.
     *
     * @return list<array{key: string, done: ?string, auto: bool, blockers?: list<string>}>
     */
    public function checklist(Separation $separation): array
    {
        $ticked = $separation->checklist ?? [];
        $employee = $separation->employee;
        $blockers = $this->clearance->blockers($employee->id);
        $settled = FinalSettlement::query()->where('employee_id', $employee->id)->value('created_at');

        return collect(Separation::CHECKLIST)
            ->reject(fn (string $key): bool => $key === 'muqeem' && Gosi::isSaudi($employee))
            ->map(fn (string $key): array => match ($key) {
                'assets' => ['key' => $key, 'done' => $blockers === [] ? 'auto' : null, 'auto' => true, 'blockers' => $blockers],
                'settlement' => ['key' => $key, 'done' => $settled?->toDateString() ?? ($ticked[$key] ?? null), 'auto' => $settled !== null],
                default => ['key' => $key, 'done' => $ticked[$key] ?? null, 'auto' => false],
            })->values()->all();
    }

    /**
     * @param  list<string>  $statuses
     */
    private function expect(Separation $separation, array $statuses): void
    {
        if (! in_array($separation->status, $statuses, true)) {
            throw ValidationException::withMessages(['action' => __('hr.separations.errors.status')]);
        }
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function move(Separation $separation, string $status, array $changes, ?Authenticatable $actor): Separation
    {
        $from = $separation->status;
        $separation->forceFill([...$changes, 'status' => $status, 'decided_by' => $actor?->getAuthIdentifier() ?? $separation->decided_by, 'decided_at' => now()])->save();
        $this->audit->record($separation, $status, ['from' => $from], $actor);

        return $separation;
    }
}
