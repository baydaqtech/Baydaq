<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Attendance\BindDevice;
use App\Modules\Hr\Actions\Attendance\ResolvePunchFlag;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Enums\PunchResolution;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\AttendanceDevice;
use App\Modules\Hr\Models\AttendancePunch;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    /**
     * A day's punches for the employees in reach, flagged ones first. Visitors from other
     * branches appear here too because the punch itself sits in this branch.
     */
    public function index(Request $request, OrgDirectory $directory): Response
    {
        $date = $request->date('date') ?? today();
        $filters = ['date' => $date->toDateString(), 'flagged' => $request->boolean('flagged')];
        $reach = $this->reach(HrFeature::Attendance, FeatureAction::View);

        $punches = AttendancePunch::query()->with('employee:id,name_ar,name_en,employee_number')
            ->whereDate('work_date', $date)
            ->where(fn ($query) => $reach->apply($query, 'hr__attendance_punches.home_branch_id', 'hr__attendance_punches.branch_id'))
            ->when($filters['flagged'], fn ($query) => $query->whereNotNull('flag')->whereNull('resolution'))
            ->orderByRaw('flag is null')->orderByDesc('punched_at')
            ->paginate(50)->withQueryString();

        $branchNames = collect($directory->branches())->mapWithKeys(fn (BranchRef $branch): array => [$branch->id => $branch->name])->all();
        $canResolve = $this->access()->allows(HrFeature::Attendance->key('resolve_flags'));

        $punches->setCollection($punches->getCollection()->map(fn (AttendancePunch $punch): array => [
            'id' => $punch->id,
            'employee_id' => $punch->employee_id,
            'employee' => $punch->employee->localized('name'),
            'employee_number' => $punch->employee->employee_number,
            'type' => $punch->type->value,
            'punched_at' => $punch->punched_at->toIso8601String(),
            'branch' => $branchNames[$punch->branch_id] ?? '—',
            'home_branch' => $branchNames[$punch->home_branch_id] ?? '—',
            'accuracy_m' => $punch->accuracy_m,
            'flag' => $punch->flag?->value,
            'resolution' => $punch->resolution?->value,
            'resolution_note' => $punch->resolution_note,
            'has_selfie' => $punch->selfie_path !== null,
            'can_resolve' => $canResolve && $punch->flag !== null && $punch->resolution === null
                && $this->access()->allowsBranch(HrFeature::Attendance->key('resolve_flags'), $punch->home_branch_id, $punch->employee->company_id ?? 0),
        ]));

        return $this->suitePage('Hr/Attendance/Index', ['punches' => $punches, 'filters' => $filters], ['hr']);
    }

    public function resolve(Request $request, AttendancePunch $punch, ResolvePunchFlag $resolve): RedirectResponse
    {
        $employee = $punch->employee;
        abort_unless($this->able($employee, HrFeature::Attendance, 'resolve_flags'), 403);
        $data = $request->validate(['resolution' => ['required', Rule::enum(PunchResolution::class)], 'note' => ['nullable', 'string', 'max:255']]);

        $resolve->handle($punch, PunchResolution::from($data['resolution']), $data['note'] ?? null, $request->user());
        $this->toast(__('hr.attendance.resolved'));

        return back();
    }

    public function resetDevice(Request $request, Employee $employee, BindDevice $bindDevice): RedirectResponse
    {
        abort_unless($this->able($employee, HrFeature::Attendance, 'reset_device'), 403);

        $bindDevice->reset($employee, $request->user());
        $this->toast(__('hr.attendance.device_reset'));

        return back();
    }

    public static function deviceFor(Employee $employee): ?AttendanceDevice
    {
        return AttendanceDevice::query()->firstWhere('employee_id', $employee->id);
    }

    /** The punch selfie, streamed from the private disk to whoever may see attendance there. */
    public function selfie(AttendancePunch $punch): StreamedResponse
    {
        abort_unless($punch->selfie_path && $this->reach(HrFeature::Attendance, FeatureAction::View)->allowsBranch($punch->branch_id ?? $punch->home_branch_id, $punch->employee->company_id), 404);

        return Storage::disk(config('baydaq.hr.disk'))->response($punch->selfie_path);
    }
}
