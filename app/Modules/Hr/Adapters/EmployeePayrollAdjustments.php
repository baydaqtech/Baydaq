<?php

namespace App\Modules\Hr\Adapters;

use App\Modules\Hr\Actions\Pay\DailyWage;
use App\Modules\Hr\Actions\Pay\SaveBonus;
use App\Modules\Hr\Actions\Pay\SaveDeduction;
use App\Modules\Hr\Enums\AdjustmentStatus;
use App\Modules\Hr\Models\BonusType;
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

    /** Deduction types other modules file under, created on first use. */
    private const KINDS = [
        'damage' => ['Damage or loss', 'تلف أو فقد'],
        'cash_shortage' => ['Cash shortage', 'عجز نقدية'],
        'staff_meal' => ['Staff meals', 'وجبات الموظفين'],
    ];

    public function __construct(private SaveDeduction $save, private DailyWage $wage, private SaveBonus $saveBonus) {}

    public function requestDeduction(int $employeeId, string $amount, string $reason, string $origin, string $kind = 'damage'): array
    {
        $employee = Employee::query()->findOrFail($employeeId);
        [$nameEn, $nameAr] = self::KINDS[$kind] ?? self::KINDS['damage'];
        $type = DeductionType::query()->firstOrCreate(['name_en' => $nameEn], ['name_ar' => $nameAr]);
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

    public function requestBonus(int $employeeId, string $amount, string $reason, string $origin): int
    {
        $type = BonusType::query()->firstOrCreate(['name_en' => 'Sales commission'], ['name_ar' => 'عمولة مبيعات']);

        return $this->saveBonus->handle(Employee::query()->findOrFail($employeeId), $type, [
            'amount' => (string) BigDecimal::of($amount)->toScale(2), 'pay_month' => Carbon::today()->addMonthNoOverflow()->startOfMonth()->toDateString(), 'reason' => "{$reason} ({$origin})",
        ])->id;
    }
}
