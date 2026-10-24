<?php

namespace App\Modules\Hr\Models;

use App\Modules\Hr\Enums\PayrollStatus;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One company's payroll for one month. Built as a draft from contracts and approved adjustments,
 * approved (lines frozen, adjustments marked), then paid (WPS file, payslips).
 */
#[Table('hr__payroll_runs')]
class PayrollRun extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pay_month' => 'date',
            'status' => PayrollStatus::class,
            'total_gross' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net' => 'decimal:2',
            'total_employer_gosi' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_on' => 'date',
        ];
    }

    /**
     * @return HasMany<PayrollLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }
}
