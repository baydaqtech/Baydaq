<?php

namespace App\Modules\Hr\Actions\Contracts;

use App\Modules\Hr\Enums\AllowanceCalculation;
use App\Modules\Hr\Enums\ContractStatus;
use App\Modules\Hr\Models\AllowanceType;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Ports\Audit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveContract
{
    public function __construct(private Audit $audit) {}

    /**
     * Create or update a contract with its allowances. Amounts are exact decimals; a percentage
     * allowance is resolved against the basic salary. Activating ends the employee's other active contract.
     *
     * @param  array{type: string, starts_on: string, ends_on?: ?string, probation_days?: int, basic_salary: numeric-string|float|int, notes?: ?string, status?: string}  $data
     * @param  list<array{allowance_type_id: int, calculation: string, value: numeric-string|float|int}>  $allowances
     */
    public function handle(Employee $employee, Contract $contract, array $data, array $allowances, ?Authenticatable $actor = null): Contract
    {
        $types = AllowanceType::query()->whereIn('id', array_column($allowances, 'allowance_type_id'))->get()->keyBy('id');

        foreach ($allowances as $line) {
            if (! $types->has($line['allowance_type_id'])) {
                throw ValidationException::withMessages(['allowances' => __('hr.contracts.errors.unknown_allowance')]);
            }
        }

        return DB::transaction(function () use ($employee, $contract, $data, $allowances, $actor): Contract {
            $status = ContractStatus::from($data['status'] ?? $contract->status->value);
            $before = $contract->exists ? (string) $contract->basic_salary : null;

            $contract->fill(collect($data)->except('status')->all());
            $contract->forceFill(['employee_id' => $employee->id, 'client_id' => $employee->client_id, 'status' => $status->value]);
            $contract->save();

            $basic = BigDecimal::of((string) $contract->basic_salary);
            $contract->allowances()->delete();

            foreach ($allowances as $line) {
                $calculation = AllowanceCalculation::from($line['calculation']);
                $value = BigDecimal::of((string) $line['value']);
                $amount = $calculation === AllowanceCalculation::PercentOfBasic
                    ? $basic->multipliedBy($value)->dividedBy(100, 2, RoundingMode::HalfUp)
                    : $value->toScale(2, RoundingMode::HalfUp);

                $contract->allowances()->make()->forceFill([
                    'allowance_type_id' => $line['allowance_type_id'],
                    'calculation' => $calculation->value,
                    'value' => (string) $value->toScale(2, RoundingMode::HalfUp),
                    'amount' => (string) $amount,
                ])->save();
            }

            if ($status === ContractStatus::Active) {
                $employee->contracts()->whereKeyNot($contract->id)->where('status', ContractStatus::Active)
                    ->update(['status' => ContractStatus::Ended->value, 'ends_on' => $contract->starts_on]);
            }

            $after = (string) $contract->basic_salary;
            $this->audit->record($contract, $contract->wasRecentlyCreated ? 'created' : 'updated',
                $before !== null && $before !== $after ? ['basic_salary' => ['from' => $before, 'to' => $after]] : null, $actor);

            return $contract->load('allowances.type');
        });
    }
}
