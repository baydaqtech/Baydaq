<?php

namespace App\Modules\Hr\Adapters;

use App\Modules\Hr\Enums\AdjustmentStatus;
use App\Modules\Hr\Enums\AdvanceStatus;
use App\Modules\Hr\Enums\BreakStatus;
use App\Modules\Hr\Enums\ChangeRequestStatus;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Enums\LeaveStatus;
use App\Modules\Hr\Models\Advance;
use App\Modules\Hr\Models\Bonus;
use App\Modules\Hr\Models\ChangeRequest;
use App\Modules\Hr\Models\Deduction;
use App\Modules\Hr\Models\ExpenseClaim;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\Overtime;
use App\Modules\Hr\Models\RemoteRequest;
use App\Modules\Hr\Models\Separation;
use App\Modules\Hr\Models\WorkBreak;
use App\Modules\Platform\Access\BranchSet;
use App\Modules\Platform\Data\ApprovalDecision;
use App\Modules\Platform\Data\ApprovalItem;
use App\Modules\Platform\Data\ScopeContext;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Ports\ApprovalSource;
use App\Modules\Platform\Ports\Entitlements;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * HR requests waiting for a decision: leave, advances and loans, bonuses and deductions, overtime,
 * expense claims, remote days, break requests, second approvals of IBAN or salary changes, and
 * resignations. Each kind needs its approve permission and only covers the employees it reaches.
 */
class HrApprovals implements ApprovalSource
{
    public function __construct(private Entitlements $entitlements) {}

    public function waiting(ScopeContext $context, int $userId): array
    {
        if (! $this->entitlements->isActive(Module::Hr)) {
            return [];
        }

        $reach = fn (HrFeature $feature, FeatureAction|string $action = FeatureAction::Approve): BranchSet => $context->reach($feature->key($action));
        // Reached, and not the approver's own request.
        $theirs = fn (BranchSet $set): Closure => fn (Builder $query) => $query->visibleIn($set)->where(fn (Builder $query) => $query->whereNull('user_id')->orWhere('user_id', '!=', $userId));
        $items = [];

        $this->collect($items, $reach(HrFeature::Leave), fn (BranchSet $set) => LeaveRequest::query()->with(['employee', 'type'])->where('status', LeaveStatus::Pending)->whereHas('employee', $theirs($set))->get(),
            fn (LeaveRequest $leave): ApprovalItem => new ApprovalItem("hr.leave:{$leave->id}", 'leave', $leave->employee->localized('name'),
                __('suite.approvals.details.leave', ['type' => $leave->type?->localized('name'), 'days' => rtrim(rtrim((string) $leave->days, '0'), '.')]),
                $leave->created_at->toIso8601String(), $leave->starts_on->toDateString(), $leave->ends_on->toDateString(), url: '/hr/leave',
                approve: new ApprovalDecision('put', "/hr/leave/{$leave->id}", ['status' => 'approved']),
                reject: new ApprovalDecision('put', "/hr/leave/{$leave->id}", ['status' => 'rejected'], 'note')));

        if ($this->entitlements->hasFeature('hr.advances')) {
            $this->collect($items, $reach(HrFeature::Pay), fn (BranchSet $set) => Advance::query()->with('employee')->where('status', AdvanceStatus::Pending)->whereHas('employee', $theirs($set))->get(),
                fn (Advance $advance): ApprovalItem => new ApprovalItem("hr.advance:{$advance->id}", $advance->kind === 'loan' ? 'loan' : 'advance', $advance->employee->localized('name'),
                    __('suite.approvals.details.advance', ['installments' => $advance->installments]),
                    $advance->created_at->toIso8601String(), amount: (string) $advance->amount, url: "/hr/employees/{$advance->employee_id}?tab=pay",
                    approve: new ApprovalDecision('put', "/hr/employees/{$advance->employee_id}/advances/{$advance->id}", ['action' => 'approve']),
                    reject: new ApprovalDecision('put', "/hr/employees/{$advance->employee_id}/advances/{$advance->id}", ['action' => 'reject'])));
        }

        foreach (['bonuses' => Bonus::class, 'deductions' => Deduction::class] as $kind => $model) {
            $this->collect($items, $reach(HrFeature::Pay), fn (BranchSet $set) => $model::query()->with(['employee', 'type'])->where('status', AdjustmentStatus::Pending)->whereHas('employee', $theirs($set))->get(),
                fn (Bonus|Deduction $adjustment): ApprovalItem => new ApprovalItem("hr.{$kind}:{$adjustment->id}", $kind === 'bonuses' ? 'bonus' : 'deduction', $adjustment->employee->localized('name'),
                    (string) $adjustment->type?->localized('name'), $adjustment->created_at->toIso8601String(), $adjustment->pay_month?->toDateString(),
                    amount: (string) $adjustment->amount, url: "/hr/employees/{$adjustment->employee_id}?tab=pay",
                    approve: new ApprovalDecision('put', "/hr/employees/{$adjustment->employee_id}/pay/{$kind}/{$adjustment->id}", ['approve' => true]),
                    reject: new ApprovalDecision('put', "/hr/employees/{$adjustment->employee_id}/pay/{$kind}/{$adjustment->id}", ['approve' => false])));
        }

        if ($this->entitlements->hasFeature('hr.overtime')) {
            $this->collect($items, $reach(HrFeature::Overtime), fn (BranchSet $set) => Overtime::query()->with('employee')->where('status', 'pending')->whereHas('employee', $theirs($set))->get(),
                fn (Overtime $overtime): ApprovalItem => new ApprovalItem("hr.overtime:{$overtime->id}", 'overtime', $overtime->employee->localized('name'),
                    __('suite.approvals.details.overtime', ['hours' => rtrim(rtrim((string) $overtime->hours, '0'), '.')]),
                    $overtime->created_at->toIso8601String(), $overtime->work_date->toDateString(), amount: $overtime->amount !== null ? (string) $overtime->amount : null, url: '/hr/overtime',
                    approve: new ApprovalDecision('put', "/hr/overtime/{$overtime->id}", ['approve' => true]),
                    reject: new ApprovalDecision('put', "/hr/overtime/{$overtime->id}", ['approve' => false])));
        }

        if ($this->entitlements->hasFeature('hr.expenses')) {
            $this->collect($items, $reach(HrFeature::Expenses), fn (BranchSet $set) => ExpenseClaim::query()->with('employee')->where('status', 'submitted')->whereHas('employee', $theirs($set))->get(),
                fn (ExpenseClaim $claim): ApprovalItem => new ApprovalItem("hr.expense:{$claim->id}", 'expense', $claim->employee->localized('name'),
                    trim(__("hr.expenses.categories.{$claim->category}").($claim->merchant ? " · {$claim->merchant}" : '')),
                    $claim->created_at->toIso8601String(), $claim->spent_on->toDateString(), amount: (string) $claim->amount, url: '/hr/expenses',
                    approve: new ApprovalDecision('put', "/hr/expenses/{$claim->id}/decision", ['approve' => true]),
                    reject: new ApprovalDecision('put', "/hr/expenses/{$claim->id}/decision", ['approve' => false], 'note')));
        }

        if ($this->entitlements->hasFeature('hr.remote')) {
            $this->collect($items, $reach(HrFeature::Remote), fn (BranchSet $set) => RemoteRequest::query()->with('employee')->where('status', 'pending')->whereHas('employee', $theirs($set))->get(),
                fn (RemoteRequest $remote): ApprovalItem => new ApprovalItem("hr.remote:{$remote->id}", 'remote', $remote->employee->localized('name'),
                    $remote->reason ?: __('suite.approvals.details.remote'), $remote->created_at->toIso8601String(), $remote->work_date->toDateString(), url: '/hr/remote',
                    approve: new ApprovalDecision('put', "/hr/remote/{$remote->id}", ['approve' => true]),
                    reject: new ApprovalDecision('put', "/hr/remote/{$remote->id}", ['approve' => false])));
        }

        if ($this->entitlements->hasFeature('hr.breaks')) {
            $this->collect($items, $reach(HrFeature::Breaks), fn (BranchSet $set) => WorkBreak::query()->with('employee')->where('status', BreakStatus::Requested)->whereDate('work_date', CarbonImmutable::now('Asia/Riyadh')->toDateString())->whereHas('employee', $theirs($set))->get(),
                fn (WorkBreak $break): ApprovalItem => new ApprovalItem("hr.break:{$break->id}", 'break', $break->employee->localized('name'),
                    __('suite.approvals.details.break', ['minutes' => $break->minutes]), ($break->requested_at ?? $break->created_at)->toIso8601String(), url: '/hr/breaks',
                    approve: new ApprovalDecision('post', "/hr/breaks/{$break->id}/decide", ['approve' => true]),
                    reject: new ApprovalDecision('post', "/hr/breaks/{$break->id}/decide", ['approve' => false])));
        }

        $this->collect($items, $reach(HrFeature::Employees, 'approve_changes'), fn (BranchSet $set) => ChangeRequest::query()->with('employee')->where('status', ChangeRequestStatus::Pending)->where(fn (Builder $query) => $query->whereNull('requested_by')->orWhere('requested_by', '!=', $userId))->whereHas('employee', fn (Builder $query) => $query->visibleIn($set))->get(),
            fn (ChangeRequest $change): ApprovalItem => new ApprovalItem("hr.change:{$change->id}", 'change', $change->employee->localized('name'),
                __("hr.changes.kind.{$change->kind}"), $change->created_at->toIso8601String(), url: "/hr/employees/{$change->employee_id}",
                approve: new ApprovalDecision('put', "/hr/changes/{$change->id}", ['decision' => 'approve']),
                reject: new ApprovalDecision('put', "/hr/changes/{$change->id}", ['decision' => 'reject'])));

        if ($this->entitlements->hasFeature('hr.offboarding')) {
            // A resignation has more than yes or no (accept, postpone): it opens on its own page.
            $this->collect($items, $reach(HrFeature::Offboarding), fn (BranchSet $set) => Separation::query()->with('employee')->where('kind', 'resignation')->where('status', 'submitted')->whereHas('employee', $theirs($set))->get(),
                fn (Separation $separation): ApprovalItem => new ApprovalItem("hr.separation:{$separation->id}", 'resignation', $separation->employee->localized('name'),
                    __('suite.approvals.details.resignation'), $separation->created_at->toIso8601String(), $separation->last_day->toDateString(), url: '/hr/separations'));
        }

        return $items;
    }

    /**
     * @template T of Model
     *
     * @param  list<ApprovalItem>  $items
     * @param  Closure(BranchSet): iterable<T>  $query
     * @param  Closure(T): ApprovalItem  $item
     */
    private function collect(array &$items, BranchSet $reach, Closure $query, Closure $item): void
    {
        if ($reach->isEmpty()) {
            return;
        }

        foreach ($query($reach) as $record) {
            $items[] = $item($record);
        }
    }
}
