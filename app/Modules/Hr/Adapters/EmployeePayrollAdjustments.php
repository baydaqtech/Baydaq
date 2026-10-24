<?php

namespace App\Modules\Hr\Adapters;

use App\Modules\Hr\Actions\Pay\DailyWage;
use App\Modules\Hr\Actions\Pay\SaveDeduction;
use App\Modules\Hr\Enums\AdjustmentStatus;
use App\Modules\Hr\Models\Deduction;
use App\Modules\Hr\Models\DeductionType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Ports\PayrollAdjustments;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeePayrollAdjustments implements PayrollAdjustments
{
    /** How far ahead a large amount may be spread. */
    private const MAX_MONTHS = 12;

    public function __construct(private SaveDeduction $save, private DailyWage $wage) {}

    public function requestDeduction(int $employeeId, string $amount, string $reason, string $origin): array
    {
        $employee = Employee::query()->findOrFail($employeeId);
        $type = DeductionType::query()->firstOrCreate(['name_en' => 'Damage or loss'], ['name_ar' => 'تلف أو فقد']);
        $daily = $this->wage->daily($this->wage->contractOf($employee));
        $monthlyCap = $daily->multipliedBy((string) config('baydaq.hr.labor.max_deduction_days_per_month'));
        $remaining = BigDecimal::of($amount);
        $month = Carbon::today()->addMonthNoOverflow()->startOfMonth();
        $created = [];

        return DB::transaction(function () use ($employee, $type, $daily, $monthlyCap, $remaining, $month, $reason, $origin, $created): array {
            for ($i = 0; $remaining->isPositive() && $i < self::MAX_MONTHS; $i++, $month = $month->copy()->addMonthNoOverflow()) {
                $taken = BigDecimal::of((string) Deduction::query()->where('employee_id', $employee->id)->whereDate('pay_month', $month)
                    ->whereIn('status', [AdjustmentStatus::Pending, AdjustmentStatus::Approved, AdjustmentStatus::Paid])->sum('amount'));
                $room = $daily->isPositive() ? $monthlyCap->minus($taken) : $remaining;
                $chunk = BigDecimal::min($remaining, $room);

                if (! $chunk->isPositive()) {
                    continue;
                }

                $deduction = $this->save->handle($employee, $type, ['amount' => (string) $chunk->toScale(2), 'pay_month' => $month->toDateString(), 'reason' => $reason, 'origin' => $origin]);
                $created[] = ['id' => $deduction->id, 'pay_month' => $month->toDateString(), 'amount' => (string) $deduction->amount];
                $remaining = $remaining->minus($chunk);
            }

            if ($remaining->isPositive()) {
                throw ValidationException::withMessages(['amount' => __('hr.pay.errors.deduction_too_large', ['months' => self::MAX_MONTHS])]);
            }

            return $created;
        });
    }
}
