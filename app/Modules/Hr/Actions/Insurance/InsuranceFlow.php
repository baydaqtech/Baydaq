<?php

namespace App\Modules\Hr\Actions\Insurance;

use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\InsuranceMember;
use App\Modules\Hr\Models\InsurancePolicy;
use App\Modules\Platform\Ports\Audit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Medical insurance: a company's policies, who is covered (employees and dependants) in which class,
 * and who is not covered yet (every employee must be insured, CCHI).
 */
class InsuranceFlow
{
    public const RELATIONS = ['self', 'spouse', 'child', 'parent'];

    public function __construct(private Audit $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function savePolicy(InsurancePolicy $policy, int $companyId, array $data, ?Authenticatable $actor = null): InsurancePolicy
    {
        $policy->fill($data);

        if (! $policy->exists) {
            $policy->forceFill(['company_id' => $companyId]);
        }

        $policy->save();
        $this->audit->record($policy, $policy->wasRecentlyCreated ? 'created' : 'updated', null, $actor);

        return $policy;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addMember(InsurancePolicy $policy, Employee $employee, array $data, ?Authenticatable $actor = null): InsuranceMember
    {
        if ($employee->company_id !== $policy->company_id) {
            throw ValidationException::withMessages(['employee_id' => __('hr.insurance.errors.company')]);
        }

        if (($data['relation'] ?? 'self') === 'self' && $this->activeSelf($employee->id)->exists()) {
            throw ValidationException::withMessages(['employee_id' => __('hr.insurance.errors.covered')]);
        }

        $member = new InsuranceMember(['relation' => 'self', ...$data]);
        $member->forceFill(['client_id' => $employee->client_id, 'policy_id' => $policy->id, 'employee_id' => $employee->id, 'status' => 'active'])->save();
        $this->audit->record($member, 'added', ['relation' => $member->relation, 'class' => $member->class], $actor);

        return $member;
    }

    public function remove(InsuranceMember $member, ?Authenticatable $actor = null): InsuranceMember
    {
        $member->forceFill(['status' => 'removed', 'ends_on' => $member->ends_on?->isPast() ? $member->ends_on : today()])->save();
        $this->audit->record($member, 'removed', null, $actor);

        return $member;
    }

    /**
     * Working employees without cover of their own.
     *
     * @param  Builder<Employee>  $employees
     * @return Builder<Employee>
     */
    public function uninsured(Builder $employees): Builder
    {
        return $employees->whereIn('status', EmployeeStatus::seated())
            ->whereNotIn('id', InsuranceMember::query()->select('employee_id')->where('relation', 'self')->where('status', 'active'));
    }

    /**
     * @return Builder<InsuranceMember>
     */
    private function activeSelf(int $employeeId): Builder
    {
        return InsuranceMember::query()->where('employee_id', $employeeId)->where('relation', 'self')->where('status', 'active');
    }
}
