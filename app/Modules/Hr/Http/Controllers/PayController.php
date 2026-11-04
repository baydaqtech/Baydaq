<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Pay\DecideAdjustment;
use App\Modules\Hr\Actions\Pay\DecideAdvance;
use App\Modules\Hr\Actions\Pay\RecordRepayment;
use App\Modules\Hr\Actions\Pay\RequestAdvance;
use App\Modules\Hr\Actions\Pay\SaveBonus;
use App\Modules\Hr\Actions\Pay\SaveBonusBatch;
use App\Modules\Hr\Actions\Pay\SaveDeduction;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Enums\RepaymentMethod;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\Advance;
use App\Modules\Hr\Models\Bonus;
use App\Modules\Hr\Models\BonusType;
use App\Modules\Hr\Models\Deduction;
use App\Modules\Hr\Models\DeductionType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Bonuses, deductions and advances of one employee, all from the profile's Pay tab.
 */
class PayController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function storeBonus(Request $request, Employee $employee, SaveBonus $save, CurrentTenant $tenant): RedirectResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Pay, FeatureAction::Create);
        $data = $request->validate([
            'bonus_type_id' => ['required', Rule::exists('hr__bonus_types', 'id')->where('client_id', $tenant->id())],
            'amount' => ['required', 'numeric', 'min:0.01'], 'pay_month' => ['required', 'date'], 'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $save->handle($employee, BonusType::query()->findOrFail($data['bonus_type_id']), $data, $request->user());
        $this->toast(__('hr.pay.saved'));

        return back();
    }

    /** The same bonus for every active employee in reach (an Eid bonus, H9). */
    public function bonusBatch(Request $request, SaveBonusBatch $save, CurrentTenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'bonus_type_id' => ['required', Rule::exists('hr__bonus_types', 'id')->where('client_id', $tenant->id())],
            'calculation' => ['required', Rule::in(['fixed', 'percent_of_basic'])], 'value' => ['required', 'numeric', 'min:0.01'],
            'pay_month' => ['required', 'date'], 'reason' => ['nullable', 'string', 'max:255'], 'company_id' => ['nullable', 'integer'],
        ]);

        $count = $save->handle($this->reach(HrFeature::Pay, FeatureAction::Create), BonusType::query()->findOrFail($data['bonus_type_id']), $data, $request->user());
        $this->toast(__('hr.pay.batch_saved', ['count' => $count]));

        return back();
    }

    public function storeDeduction(Request $request, Employee $employee, SaveDeduction $save, CurrentTenant $tenant): RedirectResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Pay, FeatureAction::Create);
        $data = $request->validate([
            'deduction_type_id' => ['required', Rule::exists('hr__deduction_types', 'id')->where('client_id', $tenant->id())],
            'amount' => ['required', 'numeric', 'min:0.01'], 'pay_month' => ['required', 'date'], 'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $save->handle($employee, DeductionType::query()->findOrFail($data['deduction_type_id']), $data, $request->user());
        $this->toast(__('hr.pay.saved'));

        return back();
    }

    public function decide(Request $request, Employee $employee, string $kind, int $id, DecideAdjustment $decide): RedirectResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Pay, FeatureAction::Approve);
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $model = $kind === 'bonuses' ? Bonus::query() : Deduction::query();

        $decide->handle($model->where('employee_id', $employee->id)->findOrFail($id), $data['approve'], $request->user());
        $this->toast(__('hr.pay.decided'));

        return back();
    }

    public function storeAdvance(Request $request, Employee $employee, RequestAdvance $requestAdvance): RedirectResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Pay, FeatureAction::Create);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'], 'installments' => ['required', 'integer', 'min:1', 'max:120'],
            'kind' => ['nullable', Rule::in(RequestAdvance::KINDS)],
            'first_installment_month' => ['required', 'date'], 'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $requestAdvance->handle($employee, $data, $request->user());
        $this->toast(__('hr.pay.saved'));

        return back();
    }

    public function advance(Request $request, Employee $employee, Advance $advance, DecideAdvance $decide, RecordRepayment $repay): RedirectResponse
    {
        abort_unless($advance->employee_id === $employee->id, 404);
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'disburse', 'repay', 'settle'])],
            'method' => ['nullable', Rule::in(['cash', 'transfer', 'payroll'])],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $this->authorizeEmployee($employee, HrFeature::Pay, in_array($data['action'], ['approve', 'reject'], true) ? FeatureAction::Approve : FeatureAction::Create);

        match ($data['action']) {
            'approve' => $decide->approve($advance, $request->user()),
            'reject' => $decide->reject($advance, $request->user()),
            'disburse' => $decide->disburse($advance, $data['method'] ?? 'transfer', $request->user()),
            'repay' => $repay->handle($advance, (string) ($data['amount'] ?? '0'), RepaymentMethod::from($data['method'] ?? 'cash'), $request->user(), reference: $data['reference'] ?? null),
            'settle' => $repay->handle($advance, '0', RepaymentMethod::Settlement, $request->user(), reference: $data['reference'] ?? null),
        };
        $this->toast(__('hr.pay.decided'));

        return back();
    }
}
