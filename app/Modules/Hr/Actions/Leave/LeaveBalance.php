<?php

namespace App\Modules\Hr\Actions\Leave;

use App\Modules\Hr\Enums\LeaveStatus;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\LeaveType;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;

class LeaveBalance
{
    /**
     * Days available of a type as of a date: the type's yearly entitlement (annual leave rises to
     * 30 days after five years of service, per the Labor Law) accrued monthly from the hire date in
     * the current year, minus approved days taken this year.
     *
     * @return array{entitled: string, taken: string, available: string}
     */
    public function handle(Employee $employee, LeaveType $type, ?Carbon $asOf = null): array
    {
        $asOf ??= today();
        $yearStart = $asOf->copy()->startOfYear();
        $hired = $employee->hired_on ?? $yearStart;

        $perYear = BigDecimal::of((string) $type->days_per_year);

        if ($perYear->isEqualTo((string) config('baydaq.hr.labor.annual_leave_days')) && $hired->diffInYears($asOf) >= 5) {
            $perYear = BigDecimal::of((string) config('baydaq.hr.labor.annual_leave_days_after_five_years'));
        }

        $from = $hired->greaterThan($yearStart) ? $hired : $yearStart;
        $monthsWorked = min(12, max(0, (int) $from->copy()->startOfMonth()->diffInMonths($asOf->copy()->endOfMonth()->addDay())));
        $entitled = $type->needs_balance ? $perYear->multipliedBy($monthsWorked)->dividedBy(12, 2, RoundingMode::Down) : BigDecimal::zero();

        $taken = BigDecimal::of((string) LeaveRequest::query()->where('employee_id', $employee->id)->where('leave_type_id', $type->id)
            ->where('status', LeaveStatus::Approved)->whereBetween('starts_on', [$yearStart, $asOf->copy()->endOfYear()])->sum('days'));

        return ['entitled' => (string) $entitled, 'taken' => (string) $taken->toScale(2), 'available' => (string) $entitled->minus($taken)];
    }
}
