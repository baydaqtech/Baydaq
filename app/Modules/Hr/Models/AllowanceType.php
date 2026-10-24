<?php

namespace App\Modules\Hr\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Hr\Database\Factories\AllowanceTypeFactory;
use App\Modules\Hr\Enums\AllowanceCalculation;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Housing, transportation, phone... Either a fixed monthly amount or a percentage of basic salary.
 */
#[Table('hr__allowance_types')]
#[Fillable(['name_ar', 'name_en', 'calculation', 'default_value', 'counts_for_gosi', 'is_active', 'sort_order'])]
class AllowanceType extends Model
{
    /** @use HasFactory<AllowanceTypeFactory> */
    use BelongsToClient, HasBilingualFields, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'default_value' => '0.00',
        'counts_for_gosi' => false,
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'calculation' => AllowanceCalculation::class,
            'default_value' => 'decimal:2',
            'counts_for_gosi' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): AllowanceTypeFactory
    {
        return AllowanceTypeFactory::new();
    }
}
