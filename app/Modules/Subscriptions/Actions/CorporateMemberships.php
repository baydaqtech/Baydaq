<?php

namespace App\Modules\Subscriptions\Actions;

use App\Modules\Hr\Data\EmployeeSummary;
use App\Modules\Hr\Ports\StaffDirectory;
use App\Modules\Subscriptions\Enums\MembershipStatus;
use App\Modules\Subscriptions\Enums\PaymentMeans;
use App\Modules\Subscriptions\Enums\PlanGender;
use App\Modules\Subscriptions\Models\Member;
use App\Modules\Subscriptions\Models\Membership;
use App\Modules\Subscriptions\Models\Plan;
use Illuminate\Validation\ValidationException;

/**
 * Corporate memberships kept in step with HR (S1): everyone employed by the company gets the
 * company's corporate plans as a member (no invoice: the company pays in bulk), and loses them
 * when they leave. Employees without a phone are skipped, since a member needs one.
 */
class CorporateMemberships
{
    public function __construct(private StaffDirectory $staff, private SaveMember $saveMember, private SellMembership $sell, private ManageMembership $manage) {}

    /** Enrol everyone currently employed by the plan's company; returns how many were enrolled. */
    public function sync(Plan $plan): int
    {
        if (! $plan->is_corporate || ! $plan->is_active) {
            return 0;
        }

        return collect($this->staff->activeInCompany($plan->company_id))->sum(fn (EmployeeSummary $employee): int => $this->enrolIn($plan, $employee) ? 1 : 0);
    }

    /** A new employee joins every corporate plan of their company. */
    public function joined(EmployeeSummary $employee): void
    {
        if ($employee->status !== 'active') {
            return;
        }

        Plan::query()->where('company_id', $employee->companyId)->where('is_corporate', true)->where('is_active', true)->get()
            ->each(fn (Plan $plan) => $this->enrolIn($plan, $employee));
    }

    /** A leaver's corporate memberships end. */
    public function left(int $employeeId): void
    {
        $member = Member::query()->where('employee_id', $employeeId)->first();

        if ($member === null) {
            return;
        }

        Membership::query()->where('member_id', $member->id)->where('status', MembershipStatus::Active)
            ->whereHas('plan', fn ($query) => $query->where('is_corporate', true))->get()
            ->each(fn (Membership $membership) => $this->manage->cancel($membership, __('subscriptions.corporate.left_company')));
    }

    private function enrolIn(Plan $plan, EmployeeSummary $employee): bool
    {
        if (! $employee->phone || ($plan->gender !== PlanGender::Any && $employee->gender !== $plan->gender->value)) {
            return false;
        }

        $member = Member::query()->where('company_id', $plan->company_id)->where('employee_id', $employee->id)->first()
            ?? Member::query()->where('company_id', $plan->company_id)->where('phone', preg_replace('/\s+/', '', $employee->phone))->first();

        try {
            $member = $this->saveMember->handle($member ?? new Member, ['name' => $employee->name, 'phone' => $employee->phone, 'gender' => $employee->gender, 'is_active' => true], $plan->company_id);
        } catch (ValidationException) {
            return false;
        }

        $member->forceFill(['employee_id' => $employee->id])->save();

        $covered = Membership::query()->where('member_id', $member->id)->where('plan_id', $plan->id)->where('status', MembershipStatus::Active)->whereDate('ends_on', '>=', today())->exists();

        if (! $covered) {
            $this->sell->handle($member, $plan, PaymentMeans::Transfer);
        }

        return true;
    }
}
