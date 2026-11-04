<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Offboarding\SeparationFlow;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Separation;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * Leavers: resignations waiting for an answer (with the day they count as accepted), accepted
 * departures with their exit checklist, and the history.
 */
class SeparationController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function index(Request $request, SeparationFlow $flow, OrgDirectory $directory): Response
    {
        $open = $request->query('view', 'open') !== 'history';
        $reach = $this->reach(HrFeature::Offboarding, FeatureAction::View);
        $approve = $this->reach(HrFeature::Offboarding, FeatureAction::Approve);
        $separations = Separation::query()->with('employee')
            ->whereHas('employee', fn ($query) => $query->visibleIn($reach))
            ->when($open, fn ($query) => $query->whereIn('status', Separation::OPEN), fn ($query) => $query->whereNotIn('status', Separation::OPEN))
            ->orderBy($open ? 'last_day' : 'updated_at', $open ? 'asc' : 'desc')->limit(200)->get();

        return $this->suitePage('Hr/Separations/Index', [
            'separations' => $separations->map(fn (Separation $separation): array => [
                ...self::separationArray($separation),
                'employee' => $separation->employee->localized('name'), 'employee_number' => $separation->employee->employee_number,
                'company' => $directory->company($separation->employee->company_id)?->name,
                'checklist' => $open ? $flow->checklist($separation) : [],
                'can_decide' => $approve->allowsBranch($separation->employee->branch_id, $separation->employee->company_id),
            ])->all(),
            'view' => $open ? 'open' : 'history',
            'max_defer_days' => (int) config('baydaq.hr.labor.resignation_max_defer_days'),
            'kinds' => Separation::KINDS,
            'employees' => $this->access()->allows(HrFeature::Offboarding->key(FeatureAction::Create))
                ? Employee::query()->visibleIn($this->reach(HrFeature::Offboarding, FeatureAction::Create))->whereIn('status', EmployeeStatus::seated())->orderBy('employee_number')->get()
                    ->map(fn (Employee $employee): array => ['value' => $employee->id, 'label' => $employee->employee_number.' · '.$employee->localized('name')])->all()
                : [],
        ], ['hr']);
    }

    public function store(Request $request, Employee $employee, SeparationFlow $flow): RedirectResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Offboarding, FeatureAction::Create);
        $data = $request->validate([
            'kind' => ['required', Rule::in(Separation::KINDS)],
            'submitted_on' => ['nullable', 'date', 'before_or_equal:today'],
            'last_day' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $flow->submit($employee, $data, 'manager', $request->user());
        $this->toast(__('hr.separations.saved'));

        return back();
    }

    public function update(Request $request, Separation $separation, SeparationFlow $flow): RedirectResponse
    {
        $this->authorizeEmployee($separation->employee, HrFeature::Offboarding, FeatureAction::Approve);
        $data = $request->validate([
            'action' => ['required', 'in:accept,defer,cancel,complete,check'],
            'last_day' => ['nullable', 'required_if:action,defer', 'date'],
            'item' => ['nullable', 'required_if:action,check', Rule::in(Separation::CHECKLIST)],
            'done' => ['boolean'],
        ]);

        match ($data['action']) {
            'accept' => $flow->accept($separation, $data['last_day'] ?? null, $request->user()),
            'defer' => $flow->defer($separation, $data['last_day'], $request->user()),
            'cancel' => $flow->cancel($separation, $request->user()),
            'complete' => $flow->complete($separation, $request->user()),
            'check' => $flow->check($separation, $data['item'], (bool) ($data['done'] ?? false), $request->user()),
        };
        $this->toast(__('hr.separations.saved'));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    public static function separationArray(Separation $separation): array
    {
        return [
            'id' => $separation->id, 'employee_id' => $separation->employee_id, 'kind' => $separation->kind, 'status' => $separation->status, 'source' => $separation->source,
            'submitted_on' => $separation->submitted_on->toDateString(), 'last_day' => $separation->last_day->toDateString(),
            'deemed_accept_on' => $separation->deemed_accept_on?->toDateString(), 'deemed' => $separation->deemed, 'reason' => $separation->reason,
            'can_withdraw' => SeparationFlow::canWithdraw($separation),
        ];
    }
}
