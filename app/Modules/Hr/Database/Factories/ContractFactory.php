<?php

namespace App\Modules\Hr\Database\Factories;

use App\Modules\Hr\Enums\ContractStatus;
use App\Modules\Hr\Enums\ContractType;
use App\Modules\Hr\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Needs client_id and employee_id from the caller.
 *
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ContractType::OpenEnded,
            'status' => ContractStatus::Active,
            'starts_on' => now()->subYear()->toDateString(),
            'probation_days' => 90,
            'basic_salary' => '6000.00',
        ];
    }
}
