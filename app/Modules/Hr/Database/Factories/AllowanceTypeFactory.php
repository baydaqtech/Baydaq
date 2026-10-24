<?php

namespace App\Modules\Hr\Database\Factories;

use App\Modules\Hr\Enums\AllowanceCalculation;
use App\Modules\Hr\Models\AllowanceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AllowanceType>
 */
class AllowanceTypeFactory extends Factory
{
    protected $model = AllowanceType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_ar' => 'بدل سكن',
            'name_en' => 'Housing',
            'calculation' => AllowanceCalculation::PercentOfBasic,
            'default_value' => '25.00',
            'counts_for_gosi' => true,
        ];
    }

    public function fixed(string $amount = '500.00'): static
    {
        return $this->state(fn (): array => ['name_ar' => 'بدل مواصلات', 'name_en' => 'Transportation', 'calculation' => AllowanceCalculation::Fixed, 'default_value' => $amount, 'counts_for_gosi' => false]);
    }
}
