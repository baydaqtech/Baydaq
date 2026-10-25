<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Breaks\BreakFlow;
use App\Modules\Hr\Actions\Expenses\ExpenseFlow;
use App\Modules\Hr\Actions\Leave\LeaveBalance;
use App\Modules\Hr\Actions\Leave\RequestLeave;
use App\Modules\Hr\Actions\Offboarding\SeparationFlow;
use App\Modules\Hr\Actions\Overtime\OvertimeFlow;
use App\Modules\Hr\Actions\Pay\AdvanceCalculator;
use App\Modules\Hr\Actions\Pay\RequestAdvance;
use App\Modules\Hr\Actions\Remote\RemoteWork;
use App\Modules\Hr\Actions\Reviews\ReviewFlow;
use App\Modules\Hr\Actions\Shifts\ClaimOpenShift;
use App\Modules\Hr\Actions\Tasks\TaskFlow;
use App\Modules\Hr\Actions\Violations\RecordViolation;
use App\Modules\Hr\Enums\TaskStatus;
use App\Modules\Hr\Http\Controllers\Api\BreakController as ApiBreakController;
use App\Modules\Hr\Models\Advance;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\ExpenseClaim;
use App\Modules\Hr\Models\InsuranceMember;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Hr\Models\Overtime;
use App\Modules\Hr\Models\RemoteRequest;
use App\Modules\Hr\Models\Review;
use App\Modules\Hr\Models\Separation;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Models\Task;
use App\Modules\Hr\Models\TrainingAttendee;
use App\Modules\Hr\Models\Violation;
use App\Modules\Hr\Models\WorkBreak;
use App\Modules\Hr\Support\HrSettings;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\Branding;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /me: an employee's own page, with or without a role. Their profile outline, رصيد الإجازات with
 * the working shown, their leave requests, and a form to ask for leave.
 */
class SelfServiceController extends Controller
{
    use RendersSuitePages;

    public function show(Request $request, LeaveBalance $balance, OrgDirectory $directory): Response
    {
        $employee = $this->employee($request);

        return Inertia::render('Hr/Me', [
            'employee' => [
                'name' => $employee->localized('name'), 'employee_number' => $employee->employee_number, 'job' => $employee->job?->localized('name'),
                'branch' => $directory->branch($employee->branch_id)?->name, 'company' => $directory->company($employee->company_id)?->name,
                'hired_on' => $employee->hired_on?->toDateString(),
            ],
            'balances' => $this->balances($employee, $balance),
            'requests' => $employee->leaveRequests()->with('type')->latest('starts_on')->limit(30)->get()->map(fn ($leave): array => LeaveController::leaveArray($leave))->all(),
            'types' => LeaveType::query()->where('is_active', true)->orderBy('sort_order')->get()->map(fn (LeaveType $type): array => ['value' => $type->id, 'label' => $type->localized('name')])->all(),
            'advance' => self::advances($employee),
            'shifts' => self::shifts($employee),
            'has_pin' => $employee->pin_hash !== null,
            'tasks' => Task::query()->with('comments')->where('assignee_id', $employee->id)->whereIn('status', [TaskStatus::Todo, TaskStatus::InProgress])
                ->orderByRaw('due_at is null')->orderBy('due_at')->limit(50)->get()
                ->map(fn (Task $task): array => TaskController::taskArray($task, $directory->branch($task->branch_id)?->name))->all(),
            'break_state' => self::breakState($employee),
            'record' => self::record($employee),
            'reviews' => self::reviews($employee),
            'remote' => self::remote($employee),
            'expenses' => self::expenses($employee),
            'overtime' => self::overtime($employee),
            'insurance' => self::insurance($employee),
            'trainings' => self::trainings($employee),
            'features' => app(Entitlements::class)->features(),
            'theme' => app(Branding::class)->theme(),
            'copy' => ['hr' => trans('hr')],
        ]);
    }

    /** Start or finish one of my tasks, with a photo when the task asks for one. */
    public function moveTask(Request $request, Task $task, TaskFlow $flow): RedirectResponse
    {
        abort_unless($task->assignee_id === $this->employee($request)->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in([TaskStatus::InProgress->value, TaskStatus::Done->value])],
            'checked' => ['nullable', 'array'], 'checked.*' => ['integer', 'min:0'],
            'photo' => ['nullable', 'image', 'max:8192'], 'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $flow->move($task, TaskStatus::from($data['status']), $request->user(), $request->file('photo'), isset($data['checked']) ? array_map('intval', $data['checked']) : null, $data['note'] ?? null);

        return back();
    }

    public function takeBreak(Request $request, BreakFlow $flow): RedirectResponse
    {
        $flow->take($this->employee($request), $request->integer('minutes') ?: null, null, $request->user());

        return back();
    }

    public function endBreak(Request $request, WorkBreak $break, BreakFlow $flow): RedirectResponse
    {
        abort_unless($break->employee_id === $this->employee($request)->id, 404);
        $flow->end($break);

        return back();
    }

    /**
     * Today's planned break and the break in progress or waiting.
     *
     * @return array<string, mixed>
     */
    public static function breakState(Employee $employee): array
    {
        $flow = app(BreakFlow::class);
        $shift = $flow->shiftToday($employee);
        $active = $flow->active($employee);

        return [
            'scheduled' => $shift?->break_at ? ['at' => substr($shift->break_at, 0, 5), 'minutes' => $shift->break_minutes] : null,
            'current' => $active ? ApiBreakController::present($active) : null,
            'self_start' => (bool) app(HrSettings::class)->get('break_self_start'),
        ];
    }

    /** Ask for a salary advance after seeing the ceiling and the plan (H18). */
    public function requestAdvance(Request $request, RequestAdvance $requestAdvance): RedirectResponse
    {
        $requestAdvance->handle($this->employee($request), self::validateAdvance($request), $request->user());
        $this->toast(__('hr.pay.advance_requested'));

        return back();
    }

    /** Set or change my till PIN (4–6 digits), used to open cash drawers (P1). */
    public function setPin(Request $request): RedirectResponse
    {
        self::savePin($this->employee($request), $request);
        $this->toast(__('hr.me.pin_saved'));

        return back();
    }

    public static function savePin(Employee $employee, Request $request): void
    {
        $data = $request->validate(['pin' => ['required', 'digits_between:4,6', 'confirmed']]);
        $employee->forceFill(['pin_hash' => Hash::make($data['pin'])])->save();
    }

    /** Pick up an open shift; away from home this makes a one-day secondment (H2). */
    public function claimShift(Request $request, Shift $shift, ClaimOpenShift $claim): RedirectResponse
    {
        $claim->handle($shift, $this->employee($request), $request->user());
        $this->toast(__('hr.shifts.claimed'));

        return back();
    }

    /**
     * The employee's coming shifts and the open shifts they may pick up.
     *
     * @return array{mine: list<array<string, mixed>>, open: list<array<string, mixed>>}
     */
    public static function shifts(Employee $employee): array
    {
        $branches = collect(app(OrgDirectory::class)->branches())->mapWithKeys(fn ($branch): array => [$branch->id => $branch->name]);
        $row = fn (Shift $shift): array => [
            'id' => $shift->id, 'date' => $shift->shift_date->toDateString(), 'starts_at' => substr($shift->starts_at, 0, 5), 'ends_at' => substr($shift->ends_at, 0, 5),
            'branch' => $branches[$shift->branch_id] ?? '—', 'ramadan_hours' => $shift->ramadan_hours, 'on_holiday' => $shift->on_holiday,
        ];

        return [
            'mine' => Shift::query()->where('employee_id', $employee->id)->whereDate('shift_date', '>=', today())->orderBy('shift_date')->limit(14)->get()->map($row)->all(),
            'open' => Shift::query()->where('is_open', true)->whereNull('employee_id')->whereDate('shift_date', '>=', today())
                ->where(fn ($query) => $query->whereNull('job_id')->orWhere('job_id', $employee->job_id))
                ->whereNotIn('shift_date', Shift::query()->where('employee_id', $employee->id)->select('shift_date'))
                ->orderBy('shift_date')->limit(20)->get()->map($row)->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function validateAdvance(Request $request): array
    {
        // Loans are their own package feature on top of advances.
        abort_if($request->input('kind') === 'loan' && ! app(Entitlements::class)->hasFeature('hr.loans'), 403, __('suite.plans.feature_off'));

        return $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'installments' => ['required', 'integer', 'min:1', 'max:120'],
            'kind' => ['nullable', Rule::in(RequestAdvance::KINDS)],
            'first_installment_month' => ['required', 'date', 'after_or_equal:'.today()->startOfMonth()->toDateString()],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * The calculator's limits and the employee's own advances.
     *
     * @return array<string, mixed>
     */
    public static function advances(Employee $employee): array
    {
        return [
            ...app(AdvanceCalculator::class)->limits($employee),
            'loan' => app(AdvanceCalculator::class)->limits($employee, 'loan'),
            'items' => Advance::query()->where('employee_id', $employee->id)->latest()->limit(10)->get()->map(fn (Advance $advance): array => [
                'id' => $advance->id, 'kind' => $advance->kind, 'amount' => (string) $advance->amount, 'installments' => $advance->installments, 'status' => $advance->status->value,
                'balance' => $advance->balance(), 'first_installment_month' => $advance->first_installment_month->format('Y-m'),
            ])->all(),
        ];
    }

    public function requestLeave(Request $request, RequestLeave $requestLeave): RedirectResponse
    {
        $employee = $this->employee($request);
        $data = $request->validate([
            'leave_type_id' => ['required', Rule::exists('hr__leave_types', 'id')->where('client_id', $employee->client_id)->where('is_active', true)],
            'starts_on' => ['required', 'date', 'after_or_equal:today'], 'ends_on' => ['required', 'date'], 'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $requestLeave->handle($employee, LeaveType::query()->findOrFail($data['leave_type_id']), $data, $request->user());
        $this->toast(__('hr.leave.requested'));

        return back();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function balances(Employee $employee, LeaveBalance $balance): array
    {
        return LeaveType::query()->where('is_active', true)->where('needs_balance', true)->orderBy('sort_order')->get()
            ->map(fn (LeaveType $type): array => ['type_id' => $type->id, 'type' => $type->localized('name'), ...$balance->handle($employee, $type)])->all();
    }

    /**
     * My leaving (the open or latest resignation) and the violations I have been told about.
     *
     * @return array{separation: ?array<string, mixed>, violations: list<array<string, mixed>>, notice_days: int}
     */
    public static function record(Employee $employee): array
    {
        $separation = Separation::query()->where('employee_id', $employee->id)->whereNotIn('status', ['cancelled'])->latest('id')->first();

        return [
            'separation' => $separation && ($separation->isOpen() || $separation->updated_at->gt(now()->subDays(30))) ? SeparationController::separationArray($separation) : null,
            'violations' => Violation::query()->where('employee_id', $employee->id)->whereNotNull('notified_at')->where('status', '!=', 'cancelled')
                ->latest('occurred_on')->limit(20)->get()->map(fn (Violation $violation): array => ViolationController::violationArray($violation))->all(),
            'notice_days' => (int) config('baydaq.hr.labor.notice_days_employee'),
        ];
    }

    /**
     * My reviews in open cycles: scoring myself, and the manager's verdict once given.
     *
     * @return list<array<string, mixed>>
     */
    public static function reviews(Employee $employee): array
    {
        return Review::query()->with('cycle')->where('employee_id', $employee->id)
            ->where(fn ($query) => $query->whereHas('cycle', fn ($query) => $query->where('status', 'open'))->orWhereNotNull('reviewed_at'))
            ->latest('id')->limit(5)->get()->map(fn (Review $review): array => [
                ...ReviewController::reviewArray($review),
                'cycle' => $review->cycle->name, 'due_on' => $review->cycle->due_on->toDateString(), 'open' => $review->cycle->status === 'open',
                'criteria' => array_map(fn (array $criterion): array => ['key' => $criterion['key'], 'label' => app()->getLocale() === 'ar' ? $criterion['label_ar'] : $criterion['label_en']], $review->cycle->criteria),
            ])->all();
    }

    /**
     * My work pattern and my requests to work from home.
     *
     * @return array{work_mode: string, remote_days: list<int>, remote_today: bool, requests: list<array<string, mixed>>}
     */
    public static function remote(Employee $employee): array
    {
        return [
            'work_mode' => $employee->work_mode, 'remote_days' => $employee->remote_days ?? [],
            'remote_today' => app(RemoteWork::class)->isRemoteDay($employee, today()->toDateString()),
            'requests' => RemoteRequest::query()->where('employee_id', $employee->id)->whereDate('work_date', '>=', today()->subDays(14))
                ->orderByDesc('work_date')->limit(20)->get()->map(fn (RemoteRequest $request): array => RemoteController::requestArray($request))->all(),
        ];
    }

    /**
     * @return array{claims: list<array<string, mixed>>, categories: list<string>}
     */
    public static function expenses(Employee $employee): array
    {
        return [
            'claims' => ExpenseClaim::query()->where('employee_id', $employee->id)->latest('spent_on')->limit(20)->get()->map(fn (ExpenseClaim $claim): array => ExpenseController::claimArray($claim))->all(),
            'categories' => ExpenseClaim::CATEGORIES,
        ];
    }

    public function claimExpense(Request $request, ExpenseFlow $flow): RedirectResponse
    {
        $flow->submit($this->employee($request), ExpenseController::validateClaim($request), $request->file('receipt'));
        $this->toast(__('hr.expenses.submitted'));

        return back();
    }

    /**
     * My medical cover and my dependants'.
     *
     * @return list<array<string, mixed>>
     */
    public static function insurance(Employee $employee): array
    {
        return InsuranceMember::query()->with('policy')->where('employee_id', $employee->id)->where('status', 'active')->orderByRaw("relation = 'self' desc")->get()
            ->map(fn (InsuranceMember $member): array => [...InsuranceController::memberArray($member), 'insurer' => $member->policy->insurer, 'policy_number' => $member->policy->policy_number, 'policy_ends_on' => $member->policy->ends_on->toDateString()])->all();
    }

    /**
     * My trainings and certificates.
     *
     * @return list<array<string, mixed>>
     */
    public static function trainings(Employee $employee): array
    {
        return TrainingAttendee::query()->with('training')->where('employee_id', $employee->id)
            ->whereHas('training', fn ($query) => $query->where('status', '!=', 'cancelled'))->latest('id')->limit(30)->get()
            ->map(fn (TrainingAttendee $attendee): array => [...TrainingController::trainingArray($attendee->training), 'attendance' => TrainingController::attendeeArray($attendee)])->all();
    }

    /** Ask to be paid for hours worked beyond the normal day. */
    public function requestOvertime(Request $request, OvertimeFlow $flow): RedirectResponse
    {
        $flow->request($this->employee($request), OvertimeController::validateOvertime($request), 'employee', $request->user());
        $this->toast(__('hr.overtime.requested'));

        return back();
    }

    public function cancelOvertime(Request $request, Overtime $overtime, OvertimeFlow $flow): RedirectResponse
    {
        abort_unless($overtime->employee_id === $this->employee($request)->id, 404);
        $flow->cancel($overtime);

        return back();
    }

    /**
     * My overtime this year and the latest requests.
     *
     * @return array<string, mixed>
     */
    public static function overtime(Employee $employee): array
    {
        return [
            'hours_this_year' => app(OvertimeFlow::class)->hoursThisYear($employee, today()->year),
            'max_hours_year' => (int) app(HrSettings::class)->get('overtime_max_hours_year'),
            'items' => Overtime::query()->where('employee_id', $employee->id)->latest('work_date')->limit(20)->get()
                ->map(fn (Overtime $overtime): array => OvertimeController::overtimeArray($overtime))->all(),
        ];
    }

    public function requestRemote(Request $request, RemoteWork $remote): RedirectResponse
    {
        $data = self::validateRemoteRequest($request);
        $remote->request($this->employee($request), $data['work_date'], $data['reason'] ?? null);
        $this->toast(__('hr.remote.requested'));

        return back();
    }

    /**
     * @return array{work_date: string, reason?: ?string}
     */
    public static function validateRemoteRequest(Request $request): array
    {
        return $request->validate(['work_date' => ['required', 'date', 'after_or_equal:today'], 'reason' => ['nullable', 'string', 'max:255']]);
    }

    public function selfReview(Request $request, Review $review, ReviewFlow $flow): RedirectResponse
    {
        abort_unless($review->employee_id === $this->employee($request)->id, 404);
        $data = $request->validate(['scores' => ['required', 'array'], 'comment' => ['nullable', 'string', 'max:3000']]);
        $flow->selfAssess($review, $data['scores'], $data['comment'] ?? null);
        $this->toast(__('hr.reviews.saved'));

        return back();
    }

    public function resign(Request $request, SeparationFlow $flow): RedirectResponse
    {
        $flow->submit($this->employee($request), [...self::validateResignation($request), 'kind' => 'resignation'], 'employee', $request->user());
        $this->toast(__('hr.separations.submitted'));

        return back();
    }

    public function withdrawResignation(Request $request, Separation $separation, SeparationFlow $flow): RedirectResponse
    {
        abort_unless($separation->employee_id === $this->employee($request)->id, 404);
        $flow->withdraw($separation, $request->user());
        $this->toast(__('hr.separations.withdrawn'));

        return back();
    }

    public function respondToViolation(Request $request, Violation $violation, RecordViolation $record): RedirectResponse
    {
        abort_unless($violation->employee_id === $this->employee($request)->id, 404);
        $record->respond($violation, $request->validate(['response' => ['required', 'string', 'max:2000']])['response'], $request->user());
        $this->toast(__('hr.violations.responded'));

        return back();
    }

    /**
     * @return array{last_day?: ?string, reason?: ?string}
     */
    public static function validateResignation(Request $request): array
    {
        return $request->validate([
            'last_day' => ['nullable', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function employee(Request $request): Employee
    {
        abort_unless(app(Entitlements::class)->isActive(Module::Hr), 403, __('suite.modules.inactive'));

        return Employee::query()->with(['job'])->where('user_id', $request->user()->id)->firstOr(fn () => abort(403, __('hr.attendance.errors.not_employee')));
    }
}
