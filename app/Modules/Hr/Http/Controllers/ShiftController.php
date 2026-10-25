<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Shifts\LoadSaudiHolidays;
use App\Modules\Hr\Actions\Shifts\SaveShift;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Holiday;
use App\Modules\Hr\Models\Job;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Support\HrSettings;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * The week's roster per branch, open shifts, and the holiday calendar (H2, H9).
 */
class ShiftController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function __construct(private OrgDirectory $directory) {}

    public function index(Request $request, HrSettings $settings): Response
    {
        $reach = $this->reach(HrFeature::Shifts, FeatureAction::View);
        $from = Carbon::parse($request->query('week', today()->toDateString()))->startOfWeek(Carbon::SUNDAY);
        $to = $from->copy()->addDays(6);
        $branches = collect($this->directory->branches())->filter(fn (BranchRef $branch): bool => $branch->isActive && $reach->allowsBranch($branch->id, $branch->companyId));
        $branchNames = $branches->mapWithKeys(fn (BranchRef $branch): array => [$branch->id => $branch->name]);

        return $this->suitePage('Hr/Shifts/Index', [
            'week' => $from->toDateString(),
            'days' => collect(range(0, 6))->map(fn (int $i): array => [
                'date' => ($day = $from->copy()->addDays($i))->toDateString(),
                'holiday' => Holiday::covering($day, 'public')?->localized('name'),
                'ramadan' => Holiday::covering($day, 'ramadan') !== null,
            ])->all(),
            'shifts' => Shift::query()->visibleIn($reach)->whereBetween('shift_date', [$from->toDateString(), $to->toDateString()])
                ->with(['employee:id,name_ar,name_en,branch_id', 'job'])->orderBy('starts_at')->get()
                ->map(fn (Shift $shift): array => [
                    'id' => $shift->id, 'date' => $shift->shift_date->toDateString(), 'branch_id' => $shift->branch_id, 'branch' => $branchNames[$shift->branch_id] ?? '—',
                    'starts_at' => substr($shift->starts_at, 0, 5), 'ends_at' => substr($shift->ends_at, 0, 5), 'minutes' => $shift->minutes,
                    'employee' => $shift->employee?->localized('name'), 'employee_id' => $shift->employee_id, 'away' => $shift->employee && $shift->employee->branch_id !== $shift->branch_id,
                    'job' => $shift->job?->localized('name'), 'is_open' => $shift->is_open, 'on_holiday' => $shift->on_holiday, 'ramadan_hours' => $shift->ramadan_hours, 'notes' => $shift->notes,
                ])->all(),
            'branches' => $branches->map(fn (BranchRef $branch): array => ['value' => $branch->id, 'label' => $branch->name])->values()->all(),
            'employees' => Employee::query()->visibleIn($this->reach(HrFeature::Shifts, FeatureAction::Create))->where('status', EmployeeStatus::Active)->orderBy('name_en')->get(['id', 'name_ar', 'name_en'])
                ->map(fn (Employee $employee): array => ['value' => $employee->id, 'label' => $employee->localized('name')])->all(),
            'jobs' => Job::query()->where('is_active', true)->orderBy('name_en')->get()->map(fn (Job $job): array => ['value' => $job->id, 'label' => $job->localized('name')])->all(),
            'holidays' => Holiday::query()->whereYear('starts_on', '>=', today()->year - 1)->orderBy('starts_on')->get()
                ->map(fn (Holiday $holiday): array => ['id' => $holiday->id, 'name' => $holiday->localized('name'), 'kind' => $holiday->kind, 'starts_on' => $holiday->starts_on->toDateString(), 'ends_on' => $holiday->ends_on->toDateString()])->all(),
            'ramadan_hours' => (int) $settings->get('ramadan_hours'),
            'saudi_years' => array_keys(LoadSaudiHolidays::CALENDAR),
        ], ['hr']);
    }

    public function store(Request $request, SaveShift $save): RedirectResponse
    {
        $data = $this->validated($request);
        $this->authorizeBranch((int) $data['branch_id'], FeatureAction::Create);
        $days = max(1, (int) ($data['repeat_days'] ?? 1));

        for ($i = 0; $i < $days; $i++) {
            $save->handle(new Shift, [...$data, 'shift_date' => Carbon::parse($data['shift_date'])->addDays($i)->toDateString()]);
        }

        $this->toast(__('hr.shifts.saved'));

        return back();
    }

    public function update(Request $request, Shift $shift, SaveShift $save): RedirectResponse
    {
        $this->authorizeBranch($shift->branch_id, FeatureAction::Update);
        $data = $this->validated($request);
        $this->authorizeBranch((int) $data['branch_id'], FeatureAction::Update);

        $save->handle($shift, $data);
        $this->toast(__('hr.shifts.saved'));

        return back();
    }

    public function destroy(Shift $shift): RedirectResponse
    {
        $this->authorizeBranch($shift->branch_id, FeatureAction::Delete);
        $shift->delete();
        $this->toast(__('hr.shifts.deleted'));

        return back();
    }

    public function storeHoliday(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:100'], 'name_en' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::in(['public', 'ramadan'])], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);

        (new Holiday($data))->forceFill(['client_id' => app(CurrentTenant::class)->id()])->save();
        $this->toast(__('hr.shifts.holiday_saved'));

        return back();
    }

    public function destroyHoliday(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();
        $this->toast(__('hr.shifts.holiday_deleted'));

        return back();
    }

    public function loadHolidays(Request $request, LoadSaudiHolidays $load): RedirectResponse
    {
        $data = $request->validate(['year' => ['required', 'integer', Rule::in(array_keys(LoadSaudiHolidays::CALENDAR))]]);
        $this->toast(__('hr.shifts.holidays_loaded', ['count' => $load->handle((int) $data['year'])]));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'branch_id' => ['required', 'integer'],
            'shift_date' => ['required', 'date'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
            'employee_id' => ['nullable', Rule::exists('hr__employees', 'id')->where('client_id', app(CurrentTenant::class)->id())],
            'job_id' => ['nullable', Rule::exists('hr__jobs', 'id')->where('client_id', app(CurrentTenant::class)->id())],
            'is_open' => ['boolean'],
            'allow_holiday' => ['boolean'],
            'repeat_days' => ['nullable', 'integer', 'min:1', 'max:14'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function authorizeBranch(int $branchId, FeatureAction $action): void
    {
        $branch = $this->directory->branch($branchId);
        abort_unless($branch && $this->reach(HrFeature::Shifts, $action)->allowsBranch($branch->id, $branch->companyId), 403);
    }
}
