<?php

namespace App\Modules\Hr\Actions\Pay;

use App\Modules\Hr\Enums\AdjustmentStatus;
use App\Modules\Hr\Models\Deduction;
use App\Modules\Hr\Models\DeductionType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Ports\Audit;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SaveDeduction
{
    public function __construct(private Audit $audit, private DailyWage $wage) {}

    /**
     * Record a deduction within the Labor Law caps: no single deduction above five days' wage, and
     * no more than five days' wage deducted in a month (approved or pending ones count). Anything
     * above the monthly cap is refused; the caller splits it over later months.
     *
     * @param  array{amount: numeric-string|float|int, pay_month: string, reason?: ?string, origin?: string}  $data
     */
    public function handle(Employee $employee, DeductionType $type, array $data, ?Authenticatable $actor = null): Deduction
    {
        $month = Carbon::parse($data['pay_month'])->startOfMonth();
        $amount = BigDecimal::of((string) $data['amount']);
        $daily = $this->wage->daily($this->wage->contractOf($employee));
        $perViolationCap = $daily->multipliedBy((string) config('baydaq.hr.labor.max_deduction_days_per_violation'));
        $monthlyCap = $daily->multipliedBy((string) config('baydaq.hr.labor.max_deduction_days_per_month'));

        if ($daily->isPositive() && $amount->isGreaterThan($perViolationCap)) {
            throw ValidationException::withMessages(['amount' => __('hr.pay.errors.deduction_cap_violation', ['max' => (string) $perViolationCap])]);
        }

        $alreadyThisMonth = BigDecimal::of((string) Deduction::query()->where('employee_id', $employee->id)
            ->whereDate('pay_month', $month)->whereIn('status', [AdjustmentStatus::Pending, AdjustmentStatus::Approved, AdjustmentStatus::Paid])->sum('amount'));

        if ($daily->isPositive() && $alreadyThisMonth->plus($amount)->isGreaterThan($monthlyCap)) {
            throw ValidationException::withMessages(['amount' => __('hr.pay.errors.deduction_cap_month', ['max' => (string) $monthlyCap, 'left' => (string) $monthlyCap->minus($alreadyThisMonth)])]);
        }

        $deduction = new Deduction(['amount' => (string) $amount, 'pay_month' => $month->toDateString(), 'reason' => $data['reason'] ?? null]);
        $deduction->forceFill([
            'client_id' => $employee->client_id,
            'employee_id' => $employee->id,
            'deduction_type_id' => $type->id,
            'status' => AdjustmentStatus::Pending->value,
            'origin' => $data['origin'] ?? 'manual',
            'requested_by' => $actor?->getAuthIdentifier(),
        ])->save();

        $this->audit->record($deduction, 'created', ['amount' => (string) $amount, 'origin' => $deduction->origin], $actor);

        return $deduction;
    }
}
