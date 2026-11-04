<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Training\TrainingFlow;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Training;
use App\Modules\Hr\Models\TrainingAttendee;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * Training: sessions per company, who is enrolled and attended, and certificates about to expire.
 */
class TrainingController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function index(OrgDirectory $directory): Response
    {
        $reach = $this->reach(HrFeature::Training, FeatureAction::View);
        $canCreate = $this->reach(HrFeature::Training, FeatureAction::Create);
        $canUpdate = $this->reach(HrFeature::Training, FeatureAction::Update);
        $visible = Employee::query()->visibleIn($reach);
        $companyIds = (clone $visible)->distinct()->pluck('company_id')->all();

        return $this->suitePage('Hr/Training/Index', [
            'trainings' => Training::query()->whereIn('company_id', $companyIds)->latest('starts_on')->limit(100)
                ->with(['attendees' => fn ($query) => $query->whereIn('employee_id', (clone $visible)->select('id'))->with('employee')])->get()
                ->map(fn (Training $training): array => [
                    ...self::trainingArray($training), 'company' => $directory->company($training->company_id)?->name,
                    'can_update' => $canUpdate->allowsWholeCompany($training->company_id) || $canCreate->allowsWholeCompany($training->company_id),
                    'attendees' => $training->attendees->map(fn (TrainingAttendee $attendee): array => [
                        ...self::attendeeArray($attendee), 'employee' => $attendee->employee->localized('name'),
                        'can_update' => $canUpdate->allowsBranch($attendee->employee->branch_id, $attendee->employee->company_id),
                    ])->all(),
                ])->all(),
            'expiring' => TrainingAttendee::query()->with(['employee', 'training'])->whereIn('employee_id', (clone $visible)->select('id'))
                ->where('status', 'attended')->whereNotNull('certificate_expires_on')->whereDate('certificate_expires_on', '<=', today()->addDays(60))
                ->orderBy('certificate_expires_on')->limit(100)->get()->map(fn (TrainingAttendee $attendee): array => [
                    'id' => $attendee->id, 'employee' => $attendee->employee->localized('name'), 'training' => $attendee->training->title,
                    'expires_on' => $attendee->certificate_expires_on->toDateString(), 'expired' => $attendee->certificate_expires_on->isPast(),
                ])->all(),
            'companies' => collect($directory->companies())->filter(fn ($company): bool => $canCreate->allowsWholeCompany($company->id))->map(fn ($company): array => ['value' => $company->id, 'label' => $company->name])->values()->all(),
            'employees' => Employee::query()->visibleIn($canCreate)->whereIn('status', EmployeeStatus::seated())->orderBy('employee_number')->get()
                ->map(fn (Employee $employee): array => ['value' => $employee->id, 'label' => $employee->localized('name').' · '.$employee->employee_number, 'company_id' => $employee->company_id])->all(),
            'statuses' => TrainingFlow::STATUSES,
            'attendance' => TrainingFlow::ATTENDANCE,
        ], ['hr']);
    }

    public function store(Request $request, TrainingFlow $flow): RedirectResponse
    {
        $data = $this->validateTraining($request) + $request->validate(['company_id' => ['required', 'integer']]);
        abort_unless($this->reach(HrFeature::Training, FeatureAction::Create)->allowsWholeCompany((int) $data['company_id']), 403);

        $flow->save(new Training, (int) $data['company_id'], collect($data)->except('company_id')->all(), $request->user());
        $this->toast(__('hr.training.saved'));

        return back();
    }

    public function update(Request $request, Training $training, TrainingFlow $flow): RedirectResponse
    {
        abort_unless($this->reach(HrFeature::Training, FeatureAction::Update)->allowsWholeCompany($training->company_id), 403);
        $flow->save($training, $training->company_id, $this->validateTraining($request) + $request->validate(['status' => ['nullable', Rule::in(TrainingFlow::STATUSES)]]), $request->user());
        $this->toast(__('hr.training.saved'));

        return back();
    }

    public function enrol(Request $request, Training $training, TrainingFlow $flow): RedirectResponse
    {
        $ids = $request->validate(['employee_ids' => ['required', 'array', 'max:200'], 'employee_ids.*' => ['integer']])['employee_ids'];
        $employees = Employee::query()->whereKey($ids)->get();

        foreach ($employees as $employee) {
            $this->authorizeEmployee($employee, HrFeature::Training, FeatureAction::Create);
        }

        DB::transaction(fn () => $employees->each(fn (Employee $employee) => $flow->enrol($training, $employee)));
        $this->toast(__('hr.training.saved'));

        return back();
    }

    public function mark(Request $request, TrainingAttendee $attendee, TrainingFlow $flow): RedirectResponse
    {
        $this->authorizeEmployee($attendee->employee, HrFeature::Training, FeatureAction::Update);
        $data = $request->validate(['status' => ['required', Rule::in(TrainingFlow::ATTENDANCE)], 'certificate_number' => ['nullable', 'string', 'max:60']]);
        $flow->mark($attendee, $data['status'], $data['certificate_number'] ?? null);
        $this->toast(__('hr.training.saved'));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    public static function trainingArray(Training $training): array
    {
        return [
            'id' => $training->id, 'company_id' => $training->company_id, 'title' => $training->title, 'provider' => $training->provider, 'location' => $training->location,
            'starts_on' => $training->starts_on->toDateString(), 'ends_on' => $training->ends_on?->toDateString(),
            'hours' => $training->hours !== null ? (string) $training->hours : null, 'cost' => $training->cost !== null ? (string) $training->cost : null,
            'certificate_months' => $training->certificate_months, 'status' => $training->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function attendeeArray(TrainingAttendee $attendee): array
    {
        return [
            'id' => $attendee->id, 'employee_id' => $attendee->employee_id, 'status' => $attendee->status,
            'certificate_number' => $attendee->certificate_number, 'certificate_expires_on' => $attendee->certificate_expires_on?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTraining(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'provider' => ['nullable', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:150'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'hours' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'certificate_months' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);
    }
}
