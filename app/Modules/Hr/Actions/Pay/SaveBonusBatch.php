<?php

namespace App\Modules\Hr\Actions\Pay;

use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Models\BonusType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Access\BranchSet;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * One bonus for everyone in reach at once (an Eid bonus, H9): a fixed amount or a share of each
 * person's basic salary. Each bonus is pending, like any other, until approved.
 */
class SaveBonusBatch
{
    public function __construct(private SaveBonus $saveBonus, private DailyWage $wage) {}

    /**
     * @param  array{calculation: 'fixed'|'percent_of_basic', value: numeric-string|float|int, pay_month: string, reason?: ?string, company_id?: ?int}  $data
     * @return int how many bonuses were created
     */
    public function handle(BranchSet $reach, BonusType $type, array $data, ?Authenticatable $actor = null): int
    {
        $value = BigDecimal::of((string) $data['value']);

        if (! $value->isPositive()) {
            throw ValidationException::withMessages(['value' => __('hr.pay.errors.batch_value')]);
        }

        $employees = Employee::query()->visibleIn($reach)->where('status', EmployeeStatus::Active)
            ->when($data['company_id'] ?? null, fn ($query, $companyId) => $query->where('company_id', $companyId))->get();

        return DB::transaction(function () use ($employees, $type, $data, $value, $actor): int {
            $created = 0;

            foreach ($employees as $employee) {
                $amount = $data['calculation'] === 'percent_of_basic'
                    ? BigDecimal::of((string) ($this->wage->contractOf($employee)?->basic_salary ?? '0'))->multipliedBy($value)->dividedBy(100, 2, RoundingMode::HalfUp)
                    : $value->toScale(2, RoundingMode::HalfUp);

                if ($amount->isPositive()) {
                    $this->saveBonus->handle($employee, $type, ['amount' => (string) $amount, 'pay_month' => $data['pay_month'], 'reason' => $data['reason'] ?? null], $actor);
                    $created++;
                }
            }

            return $created;
        });
    }
}
