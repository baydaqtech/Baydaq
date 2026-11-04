<?php

namespace App\Modules\Assets\Actions\Inventory;

use App\Modules\Assets\Enums\HolderType;
use App\Modules\Assets\Models\Warehouse;
use App\Modules\Assets\Support\Holder;
use App\Modules\Hr\Ports\StaffDirectory;
use Illuminate\Validation\ValidationException;

/**
 * What the module knows about a holder: its company, its name, and (for warehouses) the branch.
 * Employees come from HR's StaffDirectory, never HR's tables.
 */
class Holders
{
    public function __construct(private StaffDirectory $staff) {}

    /** @return array{company_id: int, branch_id: int, name: string, active: bool, employee_id: ?int} */
    public function describe(Holder $holder): array
    {
        if ($holder->type === HolderType::Warehouse) {
            $warehouse = Warehouse::query()->find($holder->id) ?? throw ValidationException::withMessages(['holder' => __('assets.errors.unknown_holder')]);

            return ['company_id' => $warehouse->company_id, 'branch_id' => $warehouse->branch_id, 'name' => $warehouse->localized('name'), 'active' => $warehouse->is_active, 'employee_id' => $warehouse->keeper_employee_id];
        }

        if ($holder->type === HolderType::Employee) {
            $employee = $this->staff->employee($holder->id) ?? throw ValidationException::withMessages(['holder' => __('assets.errors.unknown_holder')]);

            return ['company_id' => $employee->companyId, 'branch_id' => $employee->branchId, 'name' => $employee->name, 'active' => $employee->status === 'active', 'employee_id' => $employee->id];
        }

        throw ValidationException::withMessages(['holder' => __('assets.errors.unknown_holder')]);
    }

    public function name(string $type, int $id): string
    {
        return rescue(fn () => $this->describe(Holder::of($type, $id))['name'], '—', false);
    }
}
