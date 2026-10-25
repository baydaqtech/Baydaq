<?php

namespace App\Modules\Hr\Adapters;

use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Enums\LeaveStatus;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeDocument;
use App\Modules\Hr\Models\Holiday;
use App\Modules\Hr\Models\InsurancePolicy;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\RemoteRequest;
use App\Modules\Hr\Models\Survey;
use App\Modules\Hr\Models\Training;
use App\Modules\Platform\Access\BranchSet;
use App\Modules\Platform\Data\CalendarEvent;
use App\Modules\Platform\Data\ScopeContext;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Ports\CalendarSource;
use App\Modules\Platform\Ports\Entitlements;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * HR dates on the calendar: holidays, approved leave and remote days, trainings, birthdays and work
 * anniversaries, contracts and probation ending, documents expiring, insurance renewals, surveys closing.
 * Each kind needs the matching view permission and only covers the employees it reaches.
 */
class HrCalendar implements CalendarSource
{
    public function __construct(private Entitlements $entitlements) {}

    public function events(CarbonImmutable $from, CarbonImmutable $to, ScopeContext $context): array
    {
        if (! $this->entitlements->isActive(Module::Hr)) {
            return [];
        }

        $reach = fn (HrFeature $feature): BranchSet => $context->reach($feature->key(FeatureAction::View));
        $range = [$from->toDateString(), $to->toDateString()];
        $overlaps = fn (Builder $query) => $query->whereDate('starts_on', '<=', $range[1])->whereDate('ends_on', '>=', $range[0]);
        $events = [];

        // Public holidays apply to everyone who sees anything of HR.
        if (! $reach(HrFeature::Employees)->isEmpty()) {
            foreach (Holiday::query()->where('kind', 'public')->where($overlaps)->get() as $holiday) {
                $events[] = new CalendarEvent("hr.holiday:{$holiday->id}", 'holiday', (string) $holiday->localized('name'), $holiday->starts_on->toDateString(), $holiday->ends_on->toDateString(), tone: 'good');
            }
        }

        if (! ($leaveReach = $reach(HrFeature::Leave))->isEmpty()) {
            foreach (LeaveRequest::query()->with(['employee', 'type'])->where('status', LeaveStatus::Approved)->where($overlaps)->whereHas('employee', fn ($query) => $query->visibleIn($leaveReach))->get() as $leave) {
                $events[] = new CalendarEvent("hr.leave:{$leave->id}", 'leave', __('suite.calendar.leave', ['name' => $leave->employee->localized('name'), 'type' => $leave->type?->localized('name')]), $leave->starts_on->toDateString(), $leave->ends_on->toDateString(), '/hr/leave');
            }
        }

        if ($this->entitlements->hasFeature('hr.remote') && ! ($remoteReach = $reach(HrFeature::Remote))->isEmpty()) {
            foreach (RemoteRequest::query()->with('employee')->where('status', 'approved')->whereBetween('work_date', $range)->whereHas('employee', fn ($query) => $query->visibleIn($remoteReach))->get() as $remote) {
                $events[] = new CalendarEvent("hr.remote:{$remote->id}", 'remote', __('suite.calendar.remote', ['name' => $remote->employee->localized('name')]), $remote->work_date->toDateString(), url: '/hr/remote');
            }
        }

        $employeeReach = $reach(HrFeature::Employees);

        if (! $employeeReach->isEmpty()) {
            $people = Employee::query()->visibleIn($employeeReach)->whereIn('status', EmployeeStatus::seated())->get(['id', 'name_ar', 'name_en', 'birth_date', 'hired_on']);

            foreach ($this->yearsIn($from, $to) as $year) {
                foreach ($people as $employee) {
                    if ($employee->birth_date && ($day = $this->onYear($employee->birth_date, $year))->between($from, $to)) {
                        $events[] = new CalendarEvent("hr.birthday:{$employee->id}:{$year}", 'birthday', __('suite.calendar.birthday', ['name' => $employee->localized('name')]), $day->toDateString(), url: "/hr/employees/{$employee->id}", tone: 'good');
                    }

                    if ($employee->hired_on && $employee->hired_on->year < $year && ($day = $this->onYear($employee->hired_on, $year))->between($from, $to)) {
                        $events[] = new CalendarEvent("hr.anniversary:{$employee->id}:{$year}", 'anniversary', __('suite.calendar.anniversary', ['name' => $employee->localized('name'), 'years' => $year - $employee->hired_on->year]), $day->toDateString(), url: "/hr/employees/{$employee->id}", tone: 'good');
                    }
                }
            }
        }

        if (! ($contractReach = $reach(HrFeature::Contracts))->isEmpty()) {
            $contracts = Contract::query()->with('employee')->where('status', 'active')->whereHas('employee', fn ($query) => $query->visibleIn($contractReach)->whereIn('status', EmployeeStatus::seated()))->get();

            foreach ($contracts as $contract) {
                if ($contract->ends_on && $contract->ends_on->between($from, $to)) {
                    $events[] = new CalendarEvent("hr.contract_end:{$contract->id}", 'contract', __('suite.calendar.contract_ends', ['name' => $contract->employee->localized('name')]), $contract->ends_on->toDateString(), url: "/hr/employees/{$contract->employee_id}", tone: 'warn');
                }

                $probation = $contract->probation_days ? $contract->starts_on->copy()->addDays($contract->probation_days) : null;

                if ($probation && $probation->between($from, $to)) {
                    $events[] = new CalendarEvent("hr.probation:{$contract->id}", 'contract', __('suite.calendar.probation_ends', ['name' => $contract->employee->localized('name')]), $probation->toDateString(), url: "/hr/employees/{$contract->employee_id}", tone: 'warn');
                }
            }
        }

        if (! ($documentReach = $reach(HrFeature::Documents))->isEmpty()) {
            foreach (EmployeeDocument::query()->with(['employee', 'type'])->whereBetween('expires_on', $range)->whereHas('employee', fn ($query) => $query->visibleIn($documentReach)->whereIn('status', EmployeeStatus::seated()))->get() as $document) {
                $events[] = new CalendarEvent("hr.document:{$document->id}", 'document', __('suite.calendar.document_expires', ['name' => $document->employee->localized('name'), 'document' => $document->type?->localized('name')]), $document->expires_on->toDateString(), url: '/hr/documents/radar', tone: 'bad');
            }
        }

        if ($this->entitlements->hasFeature('hr.training') && ! ($trainingReach = $reach(HrFeature::Training))->isEmpty()) {
            foreach (Training::query()->whereIn('company_id', $this->companies($trainingReach))->where('status', '!=', 'cancelled')->whereDate('starts_on', '<=', $range[1])->where(fn ($query) => $query->whereDate('ends_on', '>=', $range[0])->orWhere(fn ($query) => $query->whereNull('ends_on')->whereDate('starts_on', '>=', $range[0])))->get() as $training) {
                $events[] = new CalendarEvent("hr.training:{$training->id}", 'training', $training->title, $training->starts_on->toDateString(), $training->ends_on?->toDateString(), '/hr/training');
            }
        }

        if ($this->entitlements->hasFeature('hr.insurance') && ! ($insuranceReach = $reach(HrFeature::Insurance))->isEmpty()) {
            foreach (InsurancePolicy::query()->whereIn('company_id', $this->companies($insuranceReach))->whereBetween('ends_on', $range)->get() as $policy) {
                $events[] = new CalendarEvent("hr.insurance:{$policy->id}", 'insurance', __('suite.calendar.insurance_renewal', ['insurer' => $policy->insurer]), $policy->ends_on->toDateString(), url: '/hr/insurance', tone: 'warn');
            }
        }

        if ($this->entitlements->hasFeature('hr.surveys') && ! ($surveyReach = $reach(HrFeature::Surveys))->isEmpty()) {
            $visible = Employee::query()->visibleIn($surveyReach);
            $surveys = Survey::query()->where('status', 'open')->whereBetween('closes_on', $range)
                ->when(! $surveyReach->all, fn ($query) => $query->forPlaces((clone $visible)->distinct()->pluck('company_id')->all(), (clone $visible)->distinct()->pluck('branch_id')->all()))->get();

            foreach ($surveys as $survey) {
                $events[] = new CalendarEvent("hr.survey:{$survey->id}", 'survey', __('suite.calendar.survey_closes', ['title' => $survey->title]), $survey->closes_on->toDateString(), url: '/hr/surveys');
            }
        }

        return $events;
    }

    /**
     * An employee's own dates for /me and the app: holidays, their leave, trainings and shifts are
     * listed by their own screens, so this keeps to what they'd otherwise miss.
     *
     * @return list<CalendarEvent>
     */
    public static function forEmployee(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $range = [$from->toDateString(), $to->toDateString()];
        $events = [];

        foreach (Holiday::query()->where('kind', 'public')->whereDate('starts_on', '<=', $range[1])->whereDate('ends_on', '>=', $range[0])->get() as $holiday) {
            $events[] = new CalendarEvent("hr.holiday:{$holiday->id}", 'holiday', (string) $holiday->localized('name'), $holiday->starts_on->toDateString(), $holiday->ends_on->toDateString(), tone: 'good');
        }

        foreach (LeaveRequest::query()->with('type')->where('employee_id', $employee->id)->whereIn('status', [LeaveStatus::Approved, LeaveStatus::Pending])->whereDate('starts_on', '<=', $range[1])->whereDate('ends_on', '>=', $range[0])->get() as $leave) {
            $events[] = new CalendarEvent("hr.leave:{$leave->id}", 'leave', (string) $leave->type?->localized('name'), $leave->starts_on->toDateString(), $leave->ends_on->toDateString(), tone: $leave->status === LeaveStatus::Approved ? 'info' : 'warn');
        }

        foreach (Training::query()->whereHas('attendees', fn ($query) => $query->where('employee_id', $employee->id))->where('status', '!=', 'cancelled')->whereBetween('starts_on', $range)->get() as $training) {
            $events[] = new CalendarEvent("hr.training:{$training->id}", 'training', $training->title, $training->starts_on->toDateString(), $training->ends_on?->toDateString());
        }

        foreach (Survey::query()->forEmployee($employee)->where('status', 'open')->whereBetween('closes_on', $range)->get() as $survey) {
            $events[] = new CalendarEvent("hr.survey:{$survey->id}", 'survey', __('suite.calendar.survey_closes', ['title' => $survey->title]), $survey->closes_on->toDateString());
        }

        usort($events, fn (CalendarEvent $a, CalendarEvent $b): int => $a->date <=> $b->date);

        return $events;
    }

    /**
     * @return list<int>
     */
    private function companies(BranchSet $reach): array
    {
        return Employee::query()->visibleIn($reach)->distinct()->pluck('company_id')->all();
    }

    /**
     * @return list<int>
     */
    private function yearsIn(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return range($from->year, $to->year);
    }

    /** The same month and day in another year (29 February falls on 28 February). */
    private function onYear(DateTimeInterface $date, int $year): CarbonImmutable
    {
        $date = CarbonImmutable::instance($date);

        return CarbonImmutable::create($year, $date->month, min($date->day, CarbonImmutable::create($year, $date->month, 1)->daysInMonth));
    }
}
