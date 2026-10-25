<?php

namespace App\Modules\Hr\Models;

use App\Modules\Hr\Database\Factories\ContractFactory;
use App\Modules\Hr\Enums\ContractStatus;
use App\Modules\Hr\Enums\ContractType;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An employment contract: basic salary plus allowances. One contract is active per employee;
 * activating a new one ends the previous.
 */
#[Table('hr__contracts')]
#[Fillable(['type', 'starts_on', 'ends_on', 'probation_days', 'basic_salary', 'qiwa_authenticated', 'notes'])]
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use BelongsToClient, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'probation_days' => 90,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qiwa_authenticated' => 'boolean',
            'type' => ContractType::class,
            'status' => ContractStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'probation_days' => 'integer',
            'basic_salary' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<ContractAllowance, $this>
     */
    public function allowances(): HasMany
    {
        return $this->hasMany(ContractAllowance::class);
    }

    protected static function newFactory(): ContractFactory
    {
        return ContractFactory::new();
    }
}
