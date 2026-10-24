<?php

namespace App\Modules\Hr\Actions\Leave;

use App\Modules\Hr\Enums\LeaveAccrual;
use App\Modules\Hr\Enums\LeaveStatus;
use App\Modules\Hr\Enums\LeaveYearBasis;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveAdjustment;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\LeaveType;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * رصيد الإجازات: the balance of a leave type for an employee on a date.
 *
 *   available = carried from earlier years + accrued this leave year + adjustments − approved days
 *
 * Accrual follows the type's rule: "daily" earns rate ÷ 365 per day of service, "monthly" earns
 * rate ÷ 12 per completed month, "yearly" grants the whole year up front (pro-rated in the hire
 * year). The rate is days_per_year, or senior_days_per_year once the employee has served
 * senior_after_years (21 → 30 after five years by default), counted from the exact anniversary.
 * Each finished year rolls into the next, capped at carry_over_days when set.
 */
class LeaveBalance
{
    /**
     * @return array{entitled: string, taken: string, available: string, carried: string, accrued: string, adjustments: string, pending: string,
     *     available_after_pending: string, full_year: string, rate: string, accrual: string, units: int, year_start: string, year_end: string, senior_from: ?string}
     */
    public function handle(Employee $employee, LeaveType $type, ?CarbonInterface $asOf = null): array
    {
        $asOf = CarbonImmutable::parse(($asOf ?? today())->toDateString());
        $hired = CarbonImmutable::parse(($employee->hired_on ?? $asOf)->toDateString());
        $seniorFrom = $type->senior_days_per_year !== null && $type->senior_after_years ? $hired->addYears($type->senior_after_years) : null;
        [$yearStart, $yearEnd] = $this->yearAround($type, $hired, $asOf);

        if (! $type->needs_balance) {
            return $this->result('0', '0', '0', '0', '0', '0', '0', $type, $asOf, 0, $yearStart, $yearEnd, $seniorFrom);
        }

        $requests = LeaveRequest::query()->where('employee_id', $employee->id)->where('leave_type_id', $type->id)
            ->whereIn('status', [LeaveStatus::Approved, LeaveStatus::Pending])->get(['starts_on', 'days', 'status']);
        $adjustments = LeaveAdjustment::query()->where('employee_id', $employee->id)->where('leave_type_id', $type->id)->get(['effective_on', 'days']);

        // Roll the earlier leave years forward.
        $carried = BigDecimal::zero();
        [$start, $end] = $this->yearAround($type, $hired, $hired);

        while ($start->lt($yearStart)) {
            $closing = $carried
                ->plus($this->accrue($type, $hired, $seniorFrom, max($start, $hired), $end, $end)[0])
                ->plus($this->sum($adjustments, 'effective_on', $start, $end))
                ->minus($this->sum($requests->where('status', LeaveStatus::Approved), 'starts_on', $start, $end));
            $carried = $type->carry_over_days !== null && $closing->isGreaterThan((string) $type->carry_over_days) ? BigDecimal::of((string) $type->carry_over_days) : $closing;
            [$start, $end] = $this->yearAround($type, $hired, $end->addDay());
        }

        $from = max($yearStart, $hired);
        [$accrued, $units] = $asOf->lt($hired) ? [BigDecimal::zero(), 0] : $this->accrue($type, $hired, $seniorFrom, $from, $asOf, $yearEnd);
        [$fullYear] = $this->accrue($type, $hired, $seniorFrom, $from, $yearEnd, $yearEnd);

        return $this->result(
            (string) $carried, (string) $accrued, (string) $this->sum($adjustments, 'effective_on', $yearStart, $asOf),
            (string) $this->sum($requests->where('status', LeaveStatus::Approved), 'starts_on', $yearStart, $yearEnd),
            (string) $this->sum($requests->where('status', LeaveStatus::Pending), 'starts_on', $yearStart, $yearEnd),
            (string) $fullYear, $this->rateOn($type, $seniorFrom, $asOf), $type, $asOf, $units, $yearStart, $yearEnd, $seniorFrom,
        );
    }

    /**
     * The leave year that contains a day: the calendar year, or the year from the hire anniversary.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function yearAround(LeaveType $type, CarbonImmutable $hired, CarbonImmutable $day): array
    {
        if ($type->year_basis === LeaveYearBasis::Calendar) {
            return [$day->startOfYear(), $day->endOfYear()->startOfDay()];
        }

        $start = $hired->setYear($day->year);
        $start = $start->gt($day) ? $start->subYear() : $start;

        return [$start, $start->addYear()->subDay()];
    }

    /**
     * Days earned between two dates (inclusive) and how many units (days or months) were counted.
     *
     * @return array{0: BigDecimal, 1: int}
     */
    private function accrue(LeaveType $type, CarbonImmutable $hired, ?CarbonImmutable $seniorFrom, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $yearEnd): array
    {
        if ($to->lt($from)) {
            return [BigDecimal::zero(), 0];
        }

        $base = BigDecimal::of((string) $type->days_per_year);
        $senior = BigDecimal::of((string) ($type->senior_days_per_year ?? $type->days_per_year));

        if ($type->accrual === LeaveAccrual::Monthly) {
            $months = 0;
            $earned = BigDecimal::zero();

            for ($monthEnd = $from->addMonthNoOverflow()->subDay(); $monthEnd->lte($to); $monthEnd = $from->addMonthsNoOverflow(++$months + 1)->subDay()) {
                $earned = $earned->plus(($seniorFrom && $monthEnd->gte($seniorFrom) ? $senior : $base)->dividedBy(12, 6, RoundingMode::HalfUp));
            }

            return [$earned->toScale(2, RoundingMode::Down), $months];
        }

        // Yearly grants the whole leave year on its first day; daily earns day by day.
        $until = $type->accrual === LeaveAccrual::Yearly ? $yearEnd : $to;
        $days = (int) $from->diffInDays($until) + 1;
        $seniorDays = $seniorFrom && $seniorFrom->lte($until) ? (int) max($from, $seniorFrom)->diffInDays($until) + 1 : 0;
        $earned = $base->multipliedBy($days - $seniorDays)->plus($senior->multipliedBy($seniorDays))->dividedBy(365, 2, RoundingMode::Down);

        return [BigDecimal::min($earned, $senior), $days];
    }

    private function rateOn(LeaveType $type, ?CarbonImmutable $seniorFrom, CarbonImmutable $day): string
    {
        return (string) ($seniorFrom && $day->gte($seniorFrom) ? $type->senior_days_per_year : $type->days_per_year);
    }

    /**
     * @param  Collection<int, Model>  $rows
     */
    private function sum(Collection $rows, string $dateColumn, CarbonImmutable $from, CarbonImmutable $to): BigDecimal
    {
        return $rows->filter(fn ($row): bool => $row->{$dateColumn}->toDateString() >= $from->toDateString() && $row->{$dateColumn}->toDateString() <= $to->toDateString())
            ->reduce(fn (BigDecimal $total, $row) => $total->plus((string) $row->days), BigDecimal::zero());
    }

    /**
     * @return array<string, mixed>
     */
    private function result(string $carried, string $accrued, string $adjustments, string $taken, string $pending, string $fullYear, string $rate, LeaveType $type, CarbonImmutable $asOf, int $units, CarbonImmutable $yearStart, CarbonImmutable $yearEnd, ?CarbonImmutable $seniorFrom): array
    {
        $entitled = BigDecimal::of($carried)->plus($accrued)->plus($adjustments);
        $available = $entitled->minus($taken);
        $money = fn (BigDecimal|string $value): string => (string) BigDecimal::of((string) $value)->toScale(2, RoundingMode::HalfUp);

        return [
            'entitled' => $money($entitled), 'taken' => $money($taken), 'available' => $money($available),
            'carried' => $money($carried), 'accrued' => $money($accrued), 'adjustments' => $money($adjustments), 'pending' => $money($pending),
            'available_after_pending' => $money($available->minus($pending)), 'full_year' => $money($fullYear), 'rate' => $money($rate),
            'accrual' => $type->accrual->value, 'units' => $units, 'year_start' => $yearStart->toDateString(), 'year_end' => $yearEnd->toDateString(),
            'senior_from' => $seniorFrom?->toDateString(),
        ];
    }
}
