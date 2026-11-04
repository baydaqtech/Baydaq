<?php

namespace App\Modules\Hr\Adapters;

use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Models\AttendancePunch;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeDocument;
use App\Modules\Platform\Data\BranchSignal;
use App\Modules\Platform\Data\ScopeContext;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Ports\BoardSignals;
use Carbon\CarbonImmutable;

/**
 * Attendance today (who punched in against who is on the books) and documents expiring within
 * 30 days, per branch.
 */
class HrBoardSignals implements BoardSignals
{
    public function signals(array $branchIds, ScopeContext $context): array
    {
        $today = CarbonImmutable::now('Asia/Riyadh');
        $attendanceReach = $context->reach(HrFeature::Attendance->key(FeatureAction::View));
        $documentsReach = $context->reach(HrFeature::Documents->key(FeatureAction::View));

        $headcount = Employee::query()->whereIn('branch_id', $branchIds)->where('status', EmployeeStatus::Active)
            ->selectRaw('branch_id, count(*) as total')->groupBy('branch_id')->pluck('total', 'branch_id');
        $present = AttendancePunch::query()->whereIn('home_branch_id', $branchIds)->whereDate('work_date', $today->toDateString())->where('type', 'in')
            ->selectRaw('home_branch_id, count(distinct employee_id) as total')->groupBy('home_branch_id')->pluck('total', 'home_branch_id');
        $documents = EmployeeDocument::query()->join('hr__employees', 'hr__employees.id', '=', 'hr__employee_documents.employee_id')
            ->whereIn('hr__employees.branch_id', $branchIds)->whereIn('hr__employees.status', [EmployeeStatus::Active, EmployeeStatus::OnLeave])
            ->whereNotNull('hr__employee_documents.expires_on')->whereDate('hr__employee_documents.expires_on', '<=', $today->addDays(30)->toDateString())
            ->selectRaw('hr__employees.branch_id, sum(hr__employee_documents.expires_on < ?) as expired, count(*) as total', [$today->toDateString()])
            ->groupBy('hr__employees.branch_id')->get()->keyBy('branch_id');

        $signals = [];

        foreach ($branchIds as $branchId) {
            $companyId = $context->branchCompanies[$branchId] ?? 0;
            $staff = (int) ($headcount[$branchId] ?? 0);

            if ($staff > 0 && $attendanceReach->allowsBranch($branchId, $companyId)) {
                $rate = (int) round(((int) ($present[$branchId] ?? 0)) * 100 / $staff);
                $signals[$branchId][] = new BranchSignal('attendance', __('hr.board.attendance'), "{$rate}%", $rate >= 85 ? 'good' : ($rate >= 60 ? 'warn' : 'bad'));
            }

            if ($documentsReach->allowsBranch($branchId, $companyId)) {
                $row = $documents->get($branchId);
                $expiring = (int) ($row->total ?? 0);
                $status = (int) ($row->expired ?? 0) > 0 ? 'bad' : ($expiring > 0 ? 'warn' : 'good');
                $signals[$branchId][] = new BranchSignal('documents', __('hr.board.documents'), (string) $expiring, $status);
            }
        }

        return $signals;
    }
}
