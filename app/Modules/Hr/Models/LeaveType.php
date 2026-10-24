<?php

namespace App\Modules\Hr\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Annual, sick, unpaid, Hajj... with how many days a year accrue and whether it is paid.
 */
#[Table('hr__leave_types')]
#[Fillable(['name_ar', 'name_en', 'days_per_year', 'is_paid', 'needs_balance', 'is_active', 'sort_order'])]
class LeaveType extends Model
{
    use BelongsToClient, HasBilingualFields;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'days_per_year' => '0.00',
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
        return ['days_per_year' => 'decimal:2', 'is_paid' => 'boolean', 'needs_balance' => 'boolean', 'is_active' => 'boolean'];
    }
}
