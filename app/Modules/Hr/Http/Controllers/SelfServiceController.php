<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Leave\LeaveBalance;
use App\Modules\Hr\Actions\Leave\RequestLeave;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'copy' => ['hr' => trans('hr')],
        ]);
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
