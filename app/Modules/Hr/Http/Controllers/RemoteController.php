<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Remote\RemoteWork;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\AttendancePunch;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\RemoteRequest;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * Remote and hybrid work: who works from home today, each employee's pattern, and requests for a
 * day at home.
 */
class RemoteController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function index(RemoteWork $remote): Response
    {
        $reach = $this->reach(HrFeature::Remote, FeatureAction::View);
        $employees = Employee::query()->visibleIn($reach)->whereIn('status', EmployeeStatus::seated())->orderBy('employee_number')->get();
        $today = today()->toDateString();
        $clockedIn = AttendancePunch::query()->whereIn('employee_id', $employees->pluck('id'))->whereDate('work_date', $today)->where('type', 'in')->pluck('is_remote', 'employee_id');
        $canUpdate = $this->reach(HrFeature::Remote, FeatureAction::Update);
        $canApprove = $this->reach(HrFeature::Remote, FeatureAction::Approve);

        return $this->suitePage('Hr/Remote/Index', [
            'employees' => $employees->map(fn (Employee $employee): array => [
                'id' => $employee->id, 'name' => $employee->localized('name'), 'number' => $employee->employee_number,
                'work_mode' => $employee->work_mode, 'remote_days' => $employee->remote_days ?? [],
                'remote_today' => $remote->isRemoteDay($employee, $today), 'clocked_in' => $clockedIn->has($employee->id), 'clocked_in_remote' => (bool) $clockedIn->get($employee->id),
                'can_update' => $canUpdate->allowsBranch($employee->branch_id, $employee->company_id),
            ])->all(),
            'requests' => RemoteRequest::query()->with('employee')->whereIn('employee_id', $employees->pluck('id'))
                ->where(fn ($query) => $query->where('status', 'pending')->orWhereDate('work_date', '>=', $today))
                ->orderBy('work_date')->limit(100)->get()->map(fn (RemoteRequest $request): array => [
                    ...self::requestArray($request), 'employee' => $request->employee->localized('name'),
                    'can_decide' => $canApprove->allowsBranch($request->employee->branch_id, $request->employee->company_id),
                ])->all(),
            'modes' => RemoteWork::MODES,
        ], ['hr']);
    }

    public function pattern(Request $request, Employee $employee, RemoteWork $remote): RedirectResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Remote, FeatureAction::Update);
        $data = $request->validate([
            'work_mode' => ['required', Rule::in(RemoteWork::MODES)],
            'remote_days' => ['array', 'max:6'],
            'remote_days.*' => ['integer', 'between:0,6'],
        ]);

        $remote->setPattern($employee, $data['work_mode'], $data['remote_days'] ?? []);
        $this->toast(__('hr.remote.saved'));

        return back();
    }

    public function decide(Request $request, RemoteRequest $remoteRequest, RemoteWork $remote): RedirectResponse
    {
        $this->authorizeEmployee($remoteRequest->employee, HrFeature::Remote, FeatureAction::Approve);
        $remote->decide($remoteRequest, $request->validate(['approve' => ['required', 'boolean']])['approve'], $request->user());
        $this->toast(__('hr.remote.saved'));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    public static function requestArray(RemoteRequest $request): array
    {
        return ['id' => $request->id, 'employee_id' => $request->employee_id, 'work_date' => $request->work_date->toDateString(), 'reason' => $request->reason, 'status' => $request->status];
    }
}
