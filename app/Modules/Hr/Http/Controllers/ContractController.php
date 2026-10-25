<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Contracts\SaveContract;
use App\Modules\Hr\Actions\Employees\DualControl;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Http\Requests\SaveContractRequest;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use Illuminate\Http\RedirectResponse;

class ContractController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function store(SaveContractRequest $request, Employee $employee, SaveContract $saveContract): RedirectResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Contracts, FeatureAction::Create);

        $saveContract->handle($employee, new Contract, $request->safe()->except('allowances'), $request->validated('allowances'), $request->user());
        $this->toast(app(DualControl::class)->held() === [] ? __('hr.contracts.saved') : __('hr.changes.held'));

        return back();
    }

    public function update(SaveContractRequest $request, Employee $employee, Contract $contract, SaveContract $saveContract): RedirectResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Contracts, FeatureAction::Update);
        abort_unless($contract->employee_id === $employee->id, 404);

        $saveContract->handle($employee, $contract, $request->safe()->except('allowances'), $request->validated('allowances'), $request->user());
        $this->toast(app(DualControl::class)->held() === [] ? __('hr.contracts.saved') : __('hr.changes.held'));

        return back();
    }
}
