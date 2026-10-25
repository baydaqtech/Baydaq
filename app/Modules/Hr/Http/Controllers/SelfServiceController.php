<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Breaks\BreakFlow;
use App\Modules\Hr\Actions\Leave\LeaveBalance;
use App\Modules\Hr\Actions\Leave\RequestLeave;
use App\Modules\Hr\Actions\Pay\AdvanceCalculator;
use App\Modules\Hr\Actions\Pay\RequestAdvance;
use App\Modules\Hr\Actions\Shifts\ClaimOpenShift;
use App\Modules\Hr\Actions\Tasks\TaskFlow;
use App\Modules\Hr\Enums\TaskStatus;
use App\Modules\Hr\Http\Controllers\Api\BreakController as ApiBreakController;
use App\Modules\Hr\Models\Advance;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Models\Task;
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
        return $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'installments' => ['required', 'integer', 'min:1', 'max:'.RequestAdvance::MAX_INSTALLMENTS],
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
            'items' => Advance::query()->where('employee_id', $employee->id)->latest()->limit(10)->get()->map(fn (Advance $advance): array => [
                'id' => $advance->id, 'amount' => (string) $advance->amount, 'installments' => $advance->installments, 'status' => $advance->status->value,
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

    private function employee(Request $request): Employee
    {
        abort_unless(app(Entitlements::class)->isActive(Module::Hr), 403, __('suite.modules.inactive'));

        return Employee::query()->with(['job'])->where('user_id', $request->user()->id)->firstOr(fn () => abort(403, __('hr.attendance.errors.not_employee')));
    }
}
