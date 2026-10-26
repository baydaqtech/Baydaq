<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Breaks\BreakFlow;
use App\Modules\Hr\Enums\BreakStatus;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\WorkBreak;
use App\Modules\Hr\Support\HrSettings;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * The live break board: who is on break (and late back), who asked for one, who has worked five
 * hours without a break, and everyone else clocked in, with "give a break" next to each name.
 */
class BreakController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function __construct(private BreakFlow $flow) {}

    public function index(OrgDirectory $directory, HrSettings $settings): Response
    {
        $reach = $this->reach(HrFeature::Breaks, FeatureAction::View);
        $branches = collect($directory->branches())->filter(fn (BranchRef $branch): bool => $branch->isActive && $reach->allowsBranch($branch->id, $branch->companyId))->keyBy('id');
        $today = CarbonImmutable::now('Asia/Riyadh')->toDateString();

        $breaks = WorkBreak::query()->whereIn('branch_id', $branches->keys())->whereDate('work_date', $today)->with('employee:id,name_ar,name_en')->latest('id')->get();
        $open = $breaks->whereIn('status', [BreakStatus::OnBreak, BreakStatus::Requested]);
        $clockedIn = $this->flow->clockedIn($branches->keys()->all());
        $due = $this->flow->dueForBreak($clockedIn);
        $busy = $open->pluck('employee_id')->all();
        $employees = Employee::query()->whereIn('id', $clockedIn)->get(['id', 'name_ar', 'name_en', 'branch_id']);

        $row = fn (WorkBreak $break): array => [
            'id' => $break->id, 'employee' => $break->employee?->localized('name'), 'branch' => $branches[$break->branch_id]?->name,
            'kind' => $break->kind->value, 'status' => $break->status->value, 'minutes' => $break->minutes,
            'starts_at' => $break->starts_at?->toIso8601String(), 'back_at' => $break->dueBackAt()?->toIso8601String(),
            'ends_at' => $break->ends_at?->toIso8601String(), 'overdue' => $break->isOverdue(), 'note' => $break->note,
        ];

        return $this->suitePage('Hr/Breaks/Index', [
            'on_break' => $open->where('status', BreakStatus::OnBreak)->map($row)->values()->all(),
            'requests' => $open->where('status', BreakStatus::Requested)->map($row)->values()->all(),
            'working' => $employees->reject(fn (Employee $employee): bool => in_array($employee->id, $busy, true))
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id, 'name' => $employee->localized('name'), 'branch' => $branches[$employee->branch_id]?->name,
                    'worked_minutes' => $due[$employee->id] ?? null,
                ])->sortByDesc('worked_minutes')->values()->all(),
            'ended' => $breaks->where('status', BreakStatus::Ended)->take(30)->map($row)->values()->all(),
            'settings' => ['minutes' => (int) $settings->get('break_minutes'), 'max_concurrent' => (int) $settings->get('break_max_concurrent'), 'self_start' => (bool) $settings->get('break_self_start')],
            'can_manage' => $this->access()->allows(HrFeature::Breaks->key(FeatureAction::Approve)),
        ], ['hr']);
    }

    public function grant(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless($this->reach(HrFeature::Breaks, FeatureAction::Approve)->allowsBranch($employee->branch_id, $employee->company_id), 403);
        $this->flow->grant($employee, $request->integer('minutes') ?: null, $request->user());
        $this->toast(__('hr.breaks.granted', ['name' => $employee->localized('name')]));

        return back();
    }

    public function decide(Request $request, WorkBreak $break): RedirectResponse
    {
        $this->authorizeBreak($break);
        $this->flow->decide($break, $request->validate(['approve' => ['required', 'boolean']])['approve'], $request->user());

        return back();
    }

    public function end(WorkBreak $break): RedirectResponse
    {
        $this->authorizeBreak($break);
        $this->flow->end($break);

        return back();
    }

    /** Break rules: default length, how many at once per branch, and whether employees start their own. */
    public function settings(Request $request, HrSettings $settings): RedirectResponse
    {
        abort_unless($this->access()->branches(HrFeature::Breaks->key(FeatureAction::Approve))->all, 403);
        $data = $request->validate([
            'minutes' => ['required', 'integer', 'min:15', 'max:120'],
            'max_concurrent' => ['required', 'integer', 'min:0', 'max:100'],
            'self_start' => ['required', 'boolean'],
        ]);
        $settings->set('break_minutes', (int) $data['minutes']);
        $settings->set('break_max_concurrent', (int) $data['max_concurrent']);
        $settings->set('break_self_start', (bool) $data['self_start']);
        $this->toast(__('hr.breaks.settings_saved'));

        return back();
    }

    private function authorizeBreak(WorkBreak $break): void
    {
        abort_unless($this->reach(HrFeature::Breaks, FeatureAction::Approve)->allowsBranch($break->branch_id, $break->company_id), 403);
    }
}
