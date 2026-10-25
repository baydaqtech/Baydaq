<?php

namespace App\Modules\Hr\Actions\Overtime;

use App\Modules\Hr\Actions\Pay\DailyWage;
use App\Modules\Hr\Enums\AdjustmentStatus;
use App\Modules\Hr\Models\Bonus;
use App\Modules\Hr\Models\BonusType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Overtime;
use App\Modules\Hr\Support\HrSettings;
use App\Modules\Platform\Ports\Audit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Overtime: the employee asks for hours they worked (or a manager records them), a manager approves,
 * and the approval becomes an approved bonus of the system "Overtime" type, so the hours are paid in
 * the next payroll like any bonus. Pay per hour = the hourly wage plus a share of the basic hourly
 * wage (Labor Law Art. 107: 50 %), both set in the HR rules.
 */
class OvertimeFlow
{
    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    public function __construct(private Audit $audit, private DailyWage $wage, private HrSettings $settings) {}

    /**
     * @param  array{work_date: string, hours: numeric-string|float|int, reason?: ?string}  $data
     */
    public function request(Employee $employee, array $data, string $source, ?Authenticatable $actor = null): Overtime
    {
        $date = CarbonImmutable::parse($data['work_date'])->startOfDay();

        if ($date->isFuture() || $date->lt(today()->subDays(60))) {
            throw ValidationException::withMessages(['work_date' => __('hr.overtime.errors.date')]);
        }

        return DB::transaction(function () use ($employee, $data, $date, $source, $actor): Overtime {
            Employee::query()->lockForUpdate()->whereKey($employee->id)->value('id');

            if (Overtime::query()->where('employee_id', $employee->id)->whereDate('work_date', $date)->whereIn('status', ['pending', 'approved'])->exists()) {
                throw ValidationException::withMessages(['work_date' => __('hr.overtime.errors.duplicate')]);
            }

            $overtime = (new Overtime)->forceFill([
                'client_id' => $employee->client_id, 'employee_id' => $employee->id,
                'company_id' => $employee->company_id, 'branch_id' => $employee->branch_id,
                'work_date' => $date->toDateString(), 'hours' => (string) BigDecimal::of((string) $data['hours'])->toScale(2, RoundingMode::HalfUp),
                'reason' => $data['reason'] ?? null, 'source' => $source, 'status' => 'pending',
                'requested_by' => $actor?->getAuthIdentifier(),
            ]);
            $overtime->save();
            $this->audit->record($overtime, 'requested', ['hours' => (string) $overtime->hours, 'source' => $source], $actor);

            return $overtime;
        });
    }

    public function decide(Overtime $overtime, bool $approve, ?Authenticatable $actor = null): Overtime
    {
        return DB::transaction(function () use ($overtime, $approve, $actor): Overtime {
            if (Overtime::query()->lockForUpdate()->whereKey($overtime->id)->value('status') !== 'pending') {
                throw ValidationException::withMessages(['status' => __('hr.overtime.errors.decided')]);
            }

            $overtime->refresh()->load('employee');

            if (! $approve) {
                $overtime->forceFill(['status' => 'rejected', 'decided_by' => $actor?->getAuthIdentifier(), 'decided_at' => now()])->save();
                $this->audit->record($overtime, 'rejected', null, $actor);

                return $overtime;
            }

            $employee = $overtime->employee;
            Employee::query()->lockForUpdate()->whereKey($employee->id)->value('id');
            $this->guardYearlyCap($overtime);

            $amount = $this->amount($employee, (string) $overtime->hours);
            $bonus = new Bonus(['amount' => (string) $amount, 'reason' => __('hr.overtime.bonus_reason', ['hours' => (string) $overtime->hours, 'date' => $overtime->work_date->toDateString()]), 'pay_month' => now()->startOfMonth()->toDateString()]);
            $bonus->forceFill([
                'client_id' => $employee->client_id, 'employee_id' => $employee->id, 'bonus_type_id' => $this->bonusType($employee->client_id)->id,
                'status' => AdjustmentStatus::Approved->value, 'requested_by' => $overtime->requested_by, 'approved_by' => $actor?->getAuthIdentifier(),
            ])->save();

            $overtime->forceFill(['status' => 'approved', 'amount' => (string) $amount, 'bonus_id' => $bonus->id, 'decided_by' => $actor?->getAuthIdentifier(), 'decided_at' => now()])->save();
            $this->audit->record($overtime, 'approved', ['amount' => (string) $amount], $actor);

            return $overtime;
        });
    }

    /** The employee withdraws a request nobody has answered yet. */
    public function cancel(Overtime $overtime): Overtime
    {
        return DB::transaction(function () use ($overtime): Overtime {
            if (Overtime::query()->lockForUpdate()->whereKey($overtime->id)->value('status') !== 'pending') {
                throw ValidationException::withMessages(['status' => __('hr.overtime.errors.decided')]);
            }

            $overtime->forceFill(['status' => 'cancelled'])->save();

            return $overtime;
        });
    }

    /** Pay for the given hours: hourly wage + the overtime share of the basic hourly wage. */
    public function amount(Employee $employee, string $hours): BigDecimal
    {
        $contract = $this->wage->contractOf($employee);

        if ($contract === null) {
            return BigDecimal::zero()->toScale(2);
        }

        $monthHours = BigDecimal::of((string) config('baydaq.hr.labor.month_days', 30))->multipliedBy(max(1, (int) $this->settings->get('work_hours_per_day')));
        $hourly = $this->wage->monthly($contract)->dividedBy($monthHours, 6, RoundingMode::HalfUp);
        $basicHourly = BigDecimal::of((string) $contract->basic_salary)->dividedBy($monthHours, 6, RoundingMode::HalfUp);
        $rate = $hourly->plus($basicHourly->multipliedBy((int) $this->settings->get('overtime_extra_percent'))->dividedBy(100, 6, RoundingMode::HalfUp));

        return $rate->multipliedBy($hours)->toScale(2, RoundingMode::HalfUp);
    }

    /** Approved hours this calendar year, for the yearly ceiling. */
    public function hoursThisYear(Employee $employee, int $year): string
    {
        return (string) BigDecimal::of((string) (Overtime::query()->where('employee_id', $employee->id)->where('status', 'approved')->whereYear('work_date', $year)->sum('hours') ?: '0'))->toScale(2);
    }

    private function guardYearlyCap(Overtime $overtime): void
    {
        $max = (int) $this->settings->get('overtime_max_hours_year');

        if ($max > 0 && BigDecimal::of($this->hoursThisYear($overtime->employee, $overtime->work_date->year))->plus((string) $overtime->hours)->isGreaterThan($max)) {
            throw ValidationException::withMessages(['hours' => __('hr.overtime.errors.yearly_cap', ['max' => $max])]);
        }
    }

    /** The account's own "Overtime" bonus type, created the first time it is needed. */
    private function bonusType(int $clientId): BonusType
    {
        $type = BonusType::query()->where('system_key', 'overtime')->first();

        if ($type === null) {
            $type = new BonusType(['name_ar' => 'عمل إضافي', 'name_en' => 'Overtime', 'is_active' => true, 'sort_order' => 99]);
            $type->forceFill(['client_id' => $clientId, 'system_key' => 'overtime'])->save();
        }

        return $type;
    }
}
