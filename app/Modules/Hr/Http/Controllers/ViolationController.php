<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Violations\FindLateArrivals;
use App\Modules\Hr\Actions\Violations\RecordViolation;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\DeductionType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Violation;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * The violations report: what happened in the period by kind and by person, late arrivals from
 * the roster waiting to be recorded, and each case from notice to penalty.
 */
class ViolationController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function index(Request $request, FindLateArrivals $findLate): Response
    {
        $from = (string) $request->query('from', today()->startOfMonth()->toDateString());
        $to = (string) $request->query('to', today()->toDateString());
        $category = (string) $request->query('category', '');
        $reach = $this->reach(HrFeature::Violations, FeatureAction::View);
        $canRecord = $this->access()->allows(HrFeature::Violations->key(FeatureAction::Create));

        $violations = Violation::query()->with('employee:id,employee_number,name_ar,name_en,company_id,branch_id')
            ->whereHas('employee', fn ($query) => $query->visibleIn($reach))
            ->whereBetween('occurred_on', [$from, $to])->where('status', '!=', 'cancelled')
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->latest('occurred_on')->get();
        $late = $canRecord ? $findLate->handle($this->reach(HrFeature::Violations, FeatureAction::Create), $from, $to) : [];
        $names = Employee::query()->whereIn('id', array_column($late, 'employee_id'))->get()->keyBy('id');

        return $this->suitePage('Hr/Violations/Index', [
            'violations' => $violations->map(fn (Violation $violation): array => [
                ...self::violationArray($violation),
                'employee' => $violation->employee->localized('name'), 'employee_number' => $violation->employee->employee_number,
            ])->all(),
            'by_category' => $violations->countBy('category')->all(),
            'by_employee' => $violations->groupBy('employee_id')->map(fn ($group): array => [
                'employee_id' => $group->first()->employee_id, 'employee' => $group->first()->employee->localized('name'), 'count' => $group->count(),
                'deduction_days' => (string) $group->sum('deduction_days'),
            ])->sortByDesc('count')->values()->take(10)->all(),
            'late' => array_map(fn (array $row): array => [...$row, 'employee' => $names->get($row['employee_id'])?->localized('name')], $late),
            'filters' => ['from' => $from, 'to' => $to, 'category' => $category],
            'categories' => Violation::CATEGORIES,
            'penalties' => Violation::PENALTIES,
            'deduction_types' => DeductionType::query()->get()->map(fn (DeductionType $type): array => ['value' => $type->id, 'label' => $type->localized('name')])->all(),
            'can_record' => $canRecord,
            'grace_minutes' => (int) config('baydaq.hr.labor.late_grace_minutes'),
            'can_decide' => $this->access()->allows(HrFeature::Violations->key(FeatureAction::Approve)),
            'employees' => $canRecord ? $this->employees()->whereIn('status', EmployeeStatus::seated())->orderBy('employee_number')->get()
                ->map(fn (Employee $employee): array => ['value' => $employee->id, 'label' => $employee->employee_number.' · '.$employee->localized('name')])->all() : [],
        ], ['hr']);
    }

    public function store(Request $request, Employee $employee, RecordViolation $record): RedirectResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Violations, FeatureAction::Create);
        $data = $request->validate([
            'category' => ['required', Rule::in(Violation::CATEGORIES)],
            'occurred_on' => ['required', 'date', 'before_or_equal:today'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'description' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', 'in:manual,attendance'],
        ]);

        $record->record($employee, $data, $request->user());
        $this->toast(__('hr.violations.saved'));

        return back();
    }

    public function update(Request $request, Violation $violation, RecordViolation $record): RedirectResponse
    {
        $this->authorizeEmployee($violation->employee, HrFeature::Violations, FeatureAction::Approve);
        $data = $request->validate([
            'action' => ['required', 'in:notify,close,cancel'],
            'penalty' => ['nullable', 'required_if:action,close', Rule::in(Violation::PENALTIES)],
            'deduction_days' => ['nullable', 'required_if:penalty,deduction', 'numeric', 'min:0.25', 'max:5'],
            'deduction_type_id' => ['nullable', 'required_if:penalty,deduction', 'integer'],
            'pay_month' => ['nullable', 'date'],
        ]);

        match ($data['action']) {
            'notify' => $record->notify($violation, $request->user()),
            'close' => $record->close($violation, $data, $request->user()),
            'cancel' => $record->cancel($violation, $request->user()),
        };
        $this->toast(__('hr.violations.saved'));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    public static function violationArray(Violation $violation): array
    {
        return [
            'id' => $violation->id, 'employee_id' => $violation->employee_id, 'category' => $violation->category, 'occurred_on' => $violation->occurred_on->toDateString(),
            'minutes' => $violation->minutes, 'occurrence' => $violation->occurrence, 'description' => $violation->description, 'penalty' => $violation->penalty,
            'deduction_days' => $violation->deduction_days !== null ? (string) $violation->deduction_days : null, 'status' => $violation->status,
            'employee_response' => $violation->employee_response, 'notified_at' => $violation->notified_at?->toIso8601String(), 'source' => $violation->source,
        ];
    }
}
