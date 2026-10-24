<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Movements\CancelMovement;
use App\Modules\Hr\Actions\Movements\ScheduleMovement;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Enums\MovementStatus;
use App\Modules\Hr\Enums\MovementType;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Http\Requests\ScheduleMovementRequest;
use App\Modules\Hr\Http\Resources\MovementResource;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeMovement;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class MovementController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function index(Request $request, OrgDirectory $directory): Response
    {
        $filters = $request->only(['status', 'type']);
        $employeeIds = Employee::query()->visibleIn($this->reach(HrFeature::Movements, FeatureAction::View))->select('id');

        $movements = EmployeeMovement::query()->with('employee')
            ->whereIn('employee_id', $employeeIds)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->orderByDesc('starts_on')
            ->paginate(20)
            ->withQueryString();

        $branchNames = collect($directory->branches())->mapWithKeys(fn (BranchRef $branch): array => [$branch->id => $branch->name])->all();
        $movements->setCollection($movements->getCollection()->map(fn (EmployeeMovement $movement) => new MovementResource($movement, $branchNames)));

        return $this->suitePage('Hr/Movements/Index', [
            'movements' => $movements,
            'filters' => $filters,
            'statuses' => MovementStatus::options(),
            'types' => MovementType::options(),
        ], ['hr']);
    }

    public function store(ScheduleMovementRequest $request, Employee $employee, ScheduleMovement $schedule): RedirectResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Movements, FeatureAction::Create);
        $data = $request->validated();

        $schedule->handle($employee, MovementType::from($data['type']), $data, $request->user());
        $this->toast(__('hr.movements.scheduled'));

        return back();
    }

    public function destroy(Request $request, Employee $employee, EmployeeMovement $movement, CancelMovement $cancel): RedirectResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Movements, FeatureAction::Delete);
        abort_unless($movement->employee_id === $employee->id, 404);

        $cancel->handle($movement, $request->user());
        $this->toast(__('hr.movements.cancelled'));

        return back();
    }
}
