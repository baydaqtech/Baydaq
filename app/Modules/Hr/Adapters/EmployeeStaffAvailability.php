<?php

namespace App\Modules\Hr\Adapters;

use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\LeaveStatus;
use App\Modules\Hr\Enums\PlacementKind;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeePlacement;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Ports\StaffAvailability;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class EmployeeStaffAvailability implements StaffAvailability
{
    public function availableAt(int $branchId, CarbonInterface $date): array
    {
        $day = Carbon::parse($date->toDateString());
        $placements = EmployeePlacement::query()->coveringDate($day)->get(['employee_id', 'branch_id', 'kind']);

        // A secondment that day overrides the home branch.
        $seconded = $placements->where('kind', PlacementKind::Secondment)->keyBy('employee_id');
        $here = $placements
            ->filter(fn (EmployeePlacement $placement): bool => $placement->branch_id === $branchId
                && ($placement->kind === PlacementKind::Secondment || ! $seconded->has($placement->employee_id)))
            ->pluck('employee_id')->unique();

        $onLeave = LeaveRequest::query()->where('status', LeaveStatus::Approved)
            ->whereIn('employee_id', $here)->whereDate('starts_on', '<=', $day)->whereDate('ends_on', '>=', $day)
            ->pluck('employee_id');

        return Employee::query()->whereIn('id', $here->diff($onLeave))->where('status', EmployeeStatus::Active)
            ->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }
}
