<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Overtime\OvertimeFlow;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Overtime;
use App\Modules\Hr\Support\HrSettings;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Overtime: requests waiting for a decision, recording hours for an employee, and what was approved.
 * Approved hours become an "Overtime" bonus in the next payroll.
 */
class OvertimeController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function index(Request $request, HrSettings $settings): Response
    {
        $reach = $this->reach(HrFeature::Overtime, FeatureAction::View);
        $canApprove = $this->reach(HrFeature::Overtime, FeatureAction::Approve);
        $canCreate = $this->reach(HrFeature::Overtime, FeatureAction::Create);
        $status = in_array($request->query('status'), [...OvertimeFlow::STATUSES, 'all'], true) ? $request->query('status') : 'pending';
        $employees = Employee::query()->visibleIn($reach)->whereIn('status', EmployeeStatus::seated())->orderBy('employee_number')->get();

        return $this->suitePage('Hr/Overtime/Index', [
            'items' => Overtime::query()->with('employee')->whereIn('employee_id', $employees->pluck('id'))
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->latest('work_date')->limit(200)->get()->map(fn (Overtime $overtime): array => [
                    ...self::overtimeArray($overtime), 'employee' => $overtime->employee->localized('name'),
                    'can_decide' => $canApprove->allowsBranch($overtime->branch_id, $overtime->company_id),
                ])->all(),
            'employees' => $employees->filter(fn (Employee $employee): bool => $canCreate->allowsBranch($employee->branch_id, $employee->company_id))
                ->map(fn (Employee $employee): array => ['value' => $employee->id, 'label' => $employee->localized('name').' · '.$employee->employee_number])->values()->all(),
            'status' => $status,
            'statuses' => OvertimeFlow::STATUSES,
            'rules' => ['extra_percent' => (int) $settings->get('overtime_extra_percent'), 'max_hours_year' => (int) $settings->get('overtime_max_hours_year'), 'hours_per_day' => (int) $settings->get('work_hours_per_day')],
        ], ['hr']);
    }

    public function store(Request $request, OvertimeFlow $flow): RedirectResponse
    {
        $data = self::validateOvertime($request) + $request->validate(['employee_id' => ['required', 'integer']]);
        $employee = Employee::query()->findOrFail($data['employee_id']);
        $this->authorizeEmployee($employee, HrFeature::Overtime, FeatureAction::Create);

        $flow->request($employee, $data, 'manager', $request->user());
        $this->toast(__('hr.overtime.saved'));

        return back();
    }

    public function decide(Request $request, Overtime $overtime, OvertimeFlow $flow): RedirectResponse
    {
        $this->authorizeEmployee($overtime->employee, HrFeature::Overtime, FeatureAction::Approve);
        $flow->decide($overtime, $request->validate(['approve' => ['required', 'boolean']])['approve'], $request->user());
        $this->toast(__('hr.overtime.saved'));

        return back();
    }

    /**
     * @return array{work_date: string, hours: string, reason?: ?string}
     */
    public static function validateOvertime(Request $request): array
    {
        return $request->validate([
            'work_date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:12'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function overtimeArray(Overtime $overtime): array
    {
        return [
            'id' => $overtime->id, 'employee_id' => $overtime->employee_id, 'work_date' => $overtime->work_date->toDateString(),
            'hours' => (string) $overtime->hours, 'reason' => $overtime->reason, 'source' => $overtime->source, 'status' => $overtime->status,
            'amount' => $overtime->amount !== null ? (string) $overtime->amount : null,
        ];
    }
}
