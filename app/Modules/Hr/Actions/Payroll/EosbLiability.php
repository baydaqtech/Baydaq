<?php

namespace App\Modules\Hr\Actions\Payroll;

use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Access\BranchSet;
use Brick\Math\BigDecimal;

/**
 * What the company would owe today if everyone in reach left on the employer's side (the full
 * end-of-service award, Arts. 84–85). The number accountants book as the EOSB provision.
 */
class EosbLiability
{
    public function __construct(private EndOfServiceBenefit $eosb) {}

    /**
     * @return array{total: string, companies: list<array{company_id: int, total: string, employees: int}>, employees: list<array{id: int, name: string, company_id: int, years: float, monthly_wage: string, award: string}>}
     */
    public function handle(BranchSet $reach): array
    {
        $rows = Employee::query()->visibleIn($reach)->whereIn('status', EmployeeStatus::seated())->whereNotNull('hired_on')->orderBy('company_id')->get()
            ->map(function (Employee $employee): array {
                $award = $this->eosb->handle($employee, 'termination', today());

                return ['id' => $employee->id, 'name' => $employee->localized('name'), 'company_id' => $employee->company_id, 'years' => $award['years'], 'monthly_wage' => $award['monthly_wage'], 'award' => $award['full_award']];
            });

        $sum = fn ($items): string => (string) $items->reduce(fn (BigDecimal $total, array $row): BigDecimal => $total->plus($row['award']), BigDecimal::zero())->toScale(2);

        return [
            'total' => $sum($rows),
            'companies' => $rows->groupBy('company_id')->map(fn ($items, $companyId): array => ['company_id' => (int) $companyId, 'total' => $sum($items), 'employees' => $items->count()])->values()->all(),
            'employees' => $rows->sortByDesc(fn (array $row): float => (float) $row['award'])->values()->all(),
        ];
    }
}
