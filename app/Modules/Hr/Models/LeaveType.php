<?php

namespace App\Modules\Hr\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Hr\Enums\LeaveAccrual;
use App\Modules\Hr\Enums\LeaveYearBasis;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Annual, sick, unpaid, Hajj... with how many days a year accrue and whether it is paid. Types
 * with a balance also carry the owner's rules: a higher rate after some years of service, how
 * the days build up, when the leave year starts, and how many days roll over.
 */
#[Table('hr__leave_types')]
#[Fillable(['name_ar', 'name_en', 'days_per_year', 'senior_days_per_year', 'senior_after_years', 'accrual', 'year_basis', 'carry_over_days', 'allow_negative', 'is_paid', 'needs_balance', 'is_active', 'sort_order'])]
class LeaveType extends Model
{
    use BelongsToClient, HasBilingualFields;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'days_per_year' => '0.00',
        'accrual' => 'monthly',
        'year_basis' => 'calendar',
        'allow_negative' => false,
        'is_paid' => true,
        'needs_balance' => true,
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days_per_year' => 'decimal:2', 'senior_days_per_year' => 'decimal:2', 'senior_after_years' => 'integer', 'carry_over_days' => 'decimal:2',
            'accrual' => LeaveAccrual::class, 'year_basis' => LeaveYearBasis::class,
            'allow_negative' => 'boolean', 'is_paid' => 'boolean', 'needs_balance' => 'boolean', 'is_active' => 'boolean',
        ];
    }
}
