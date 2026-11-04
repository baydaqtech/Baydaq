<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Insurance\InsuranceFlow;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\InsuranceMember;
use App\Modules\Hr\Models\InsurancePolicy;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * Medical insurance: each company's policies, who is covered in which class, renewals coming up, and
 * the employees nobody has insured yet.
 */
class InsuranceController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function index(InsuranceFlow $flow, OrgDirectory $directory): Response
    {
        $reach = $this->reach(HrFeature::Insurance, FeatureAction::View);
        $canCreate = $this->reach(HrFeature::Insurance, FeatureAction::Create);
        $canUpdate = $this->reach(HrFeature::Insurance, FeatureAction::Update);
        $visible = Employee::query()->visibleIn($reach);
        $companyIds = (clone $visible)->distinct()->pluck('company_id')->all();
        $soon = today()->addDays(60);

        $policies = InsurancePolicy::query()->whereIn('company_id', $companyIds)->latest('ends_on')
            ->with(['members' => fn ($query) => $query->where('status', 'active')->whereIn('employee_id', (clone $visible)->select('id'))->with('employee')])->get();

        return $this->suitePage('Hr/Insurance/Index', [
            'policies' => $policies->map(fn (InsurancePolicy $policy): array => [
                'id' => $policy->id, 'company' => $directory->company($policy->company_id)?->name, 'company_id' => $policy->company_id,
                'insurer' => $policy->insurer, 'policy_number' => $policy->policy_number, 'starts_on' => $policy->starts_on->toDateString(), 'ends_on' => $policy->ends_on->toDateString(),
                'notes' => $policy->notes, 'renewal_due' => $policy->ends_on->lte($soon), 'can_update' => $canUpdate->allowsWholeCompany($policy->company_id),
                'members' => $policy->members->map(fn (InsuranceMember $member): array => [
                    ...self::memberArray($member), 'employee' => $member->employee->localized('name'),
                    'can_update' => $canUpdate->allowsBranch($member->employee->branch_id, $member->employee->company_id),
                ])->all(),
            ])->all(),
            'uninsured' => $flow->uninsured(Employee::query()->visibleIn($reach))->orderBy('employee_number')->get()
                ->map(fn (Employee $employee): array => ['id' => $employee->id, 'name' => $employee->localized('name'), 'number' => $employee->employee_number, 'company_id' => $employee->company_id])->all(),
            'companies' => collect($directory->companies())->filter(fn ($company): bool => $canCreate->allowsWholeCompany($company->id))->map(fn ($company): array => ['value' => $company->id, 'label' => $company->name])->values()->all(),
            'employees' => Employee::query()->visibleIn($canCreate)->whereIn('status', EmployeeStatus::seated())->orderBy('employee_number')->get()
                ->map(fn (Employee $employee): array => ['value' => $employee->id, 'label' => $employee->localized('name').' · '.$employee->employee_number, 'company_id' => $employee->company_id])->all(),
            'relations' => InsuranceFlow::RELATIONS,
        ], ['hr']);
    }

    public function storePolicy(Request $request, InsuranceFlow $flow): RedirectResponse
    {
        $data = $this->validatePolicy($request) + $request->validate(['company_id' => ['required', 'integer']]);
        abort_unless($this->reach(HrFeature::Insurance, FeatureAction::Create)->allowsWholeCompany((int) $data['company_id']), 403);

        $flow->savePolicy(new InsurancePolicy, (int) $data['company_id'], collect($data)->except('company_id')->all(), $request->user());
        $this->toast(__('hr.insurance.saved'));

        return back();
    }

    public function updatePolicy(Request $request, InsurancePolicy $policy, InsuranceFlow $flow): RedirectResponse
    {
        abort_unless($this->reach(HrFeature::Insurance, FeatureAction::Update)->allowsWholeCompany($policy->company_id), 403);
        $flow->savePolicy($policy, $policy->company_id, $this->validatePolicy($request), $request->user());
        $this->toast(__('hr.insurance.saved'));

        return back();
    }

    public function storeMember(Request $request, InsurancePolicy $policy, InsuranceFlow $flow): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'relation' => ['required', Rule::in(InsuranceFlow::RELATIONS)],
            'name' => ['nullable', 'required_unless:relation,self', 'string', 'max:150'],
            'class' => ['required', 'string', 'max:10'],
            'card_number' => ['nullable', 'string', 'max:60'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'premium' => ['nullable', 'numeric', 'min:0'],
        ]);
        $employee = Employee::query()->findOrFail($data['employee_id']);
        $this->authorizeEmployee($employee, HrFeature::Insurance, FeatureAction::Create);

        $flow->addMember($policy, $employee, collect($data)->except('employee_id')->all(), $request->user());
        $this->toast(__('hr.insurance.saved'));

        return back();
    }

    public function removeMember(Request $request, InsuranceMember $member, InsuranceFlow $flow): RedirectResponse
    {
        $this->authorizeEmployee($member->employee, HrFeature::Insurance, FeatureAction::Update);
        $flow->remove($member, $request->user());
        $this->toast(__('hr.insurance.saved'));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    public static function memberArray(InsuranceMember $member): array
    {
        return [
            'id' => $member->id, 'employee_id' => $member->employee_id, 'relation' => $member->relation, 'name' => $member->name, 'class' => $member->class,
            'card_number' => $member->card_number, 'starts_on' => $member->starts_on->toDateString(), 'ends_on' => $member->ends_on?->toDateString(),
            'premium' => $member->premium !== null ? (string) $member->premium : null, 'status' => $member->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePolicy(Request $request): array
    {
        return $request->validate([
            'insurer' => ['required', 'string', 'max:150'],
            'policy_number' => ['nullable', 'string', 'max:60'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
