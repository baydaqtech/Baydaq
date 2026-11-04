<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Leave\AdjustLeaveBalance;
use App\Modules\Hr\Actions\Leave\DecideLeave;
use App\Modules\Hr\Actions\Leave\RequestLeave;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Enums\LeaveStatus;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

class LeaveController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function index(Request $request): Response
    {
        $filters = $request->only(['status']);
        $employeeIds = Employee::query()->visibleIn($this->reach(HrFeature::Leave, FeatureAction::View))->select('id');

        $requests = LeaveRequest::query()->with(['employee:id,name_ar,name_en', 'type'])
            ->whereIn('employee_id', $employeeIds)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByRaw("status = 'pending' desc")->orderByDesc('starts_on')
            ->paginate(20)->withQueryString();

        $canApprove = $this->access()->allows(HrFeature::Leave->key('approve'));
        $requests->setCollection($requests->getCollection()->map(fn (LeaveRequest $leave): array => [
            ...self::leaveArray($leave),
            'employee' => $leave->employee->localized('name'),
            'can_decide' => $canApprove && $this->reach(HrFeature::Leave, FeatureAction::Approve)->allowsBranch($leave->employee->branch_id ?? 0, $leave->employee->company_id ?? 0),
        ]));

        return $this->suitePage('Hr/Leave/Index', ['requests' => $requests, 'filters' => $filters, 'statuses' => LeaveStatus::options()], ['hr']);
    }

    public function store(Request $request, Employee $employee, RequestLeave $requestLeave, CurrentTenant $tenant): RedirectResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Leave, FeatureAction::Create);
        $data = $request->validate([
            'leave_type_id' => ['required', Rule::exists('hr__leave_types', 'id')->where('client_id', $tenant->id())],
            'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date'], 'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $requestLeave->handle($employee, LeaveType::query()->findOrFail($data['leave_type_id']), $data, $request->user());
        $this->toast(__('hr.leave.requested'));

        return back();
    }

    public function decide(Request $request, LeaveRequest $leave, DecideLeave $decide): RedirectResponse
    {
        $this->authorizeEmployee($leave->employee, HrFeature::Leave, FeatureAction::Approve);
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected', 'cancelled'])], 'note' => ['nullable', 'string', 'max:255']]);

        $decide->handle($leave, LeaveStatus::from($data['status']), $data['note'] ?? null, $request->user());
        $this->toast(__('hr.leave.decided'));

        return back();
    }

    public function adjust(Request $request, Employee $employee, AdjustLeaveBalance $adjust, CurrentTenant $tenant): RedirectResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Leave, FeatureAction::Approve);
        $data = $request->validate([
            'leave_type_id' => ['required', Rule::exists('hr__leave_types', 'id')->where('client_id', $tenant->id())],
            'days' => ['required', 'numeric', 'between:-365,365'], 'effective_on' => ['required', 'date'], 'reason' => ['required', 'string', 'max:255'],
        ]);

        $adjust->handle($employee, LeaveType::query()->findOrFail($data['leave_type_id']), (string) $data['days'], $data['effective_on'], $data['reason'], $request->user());
        $this->toast(__('hr.leave.adjust.saved'));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    public static function leaveArray(LeaveRequest $leave): array
    {
        return [
            'id' => $leave->id,
            'employee_id' => $leave->employee_id,
            'type' => $leave->type->localized('name'),
            'is_paid' => $leave->type->is_paid,
            'starts_on' => $leave->starts_on->toDateString(),
            'ends_on' => $leave->ends_on->toDateString(),
            'days' => (string) $leave->days,
            'status' => $leave->status->value,
            'reason' => $leave->reason,
            'decision_note' => $leave->decision_note,
        ];
    }
}
