<?php

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employee's payslip inside a run.
 *
 * @property list<array{kind: string, label_ar: string, label_en: string, amount: string}> $breakdown
 */
#[Table('hr__payroll_lines')]
class PayrollLine extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'basic' => 'decimal:2', 'allowances' => 'decimal:2', 'bonuses' => 'decimal:2', 'deductions' => 'decimal:2',
            'advance_installments' => 'decimal:2', 'unpaid_leave' => 'decimal:2', 'gosi_employee' => 'decimal:2',
            'gosi_employer' => 'decimal:2', 'gross' => 'decimal:2', 'net' => 'decimal:2', 'unpaid_leave_days' => 'decimal:2',
            'breakdown' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
