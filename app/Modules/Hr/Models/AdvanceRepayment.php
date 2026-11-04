<?php

namespace App\Modules\Hr\Models;

use App\Modules\Hr\Enums\RepaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Money that came back on an advance: a payroll installment, cash, a transfer, or a settlement.
 */
#[Table('hr__advance_repayments')]
class AdvanceRepayment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_on' => 'date', 'method' => RepaymentMethod::class];
    }
}
