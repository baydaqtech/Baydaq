<?php

namespace App\Modules\Hr\Database\Factories;

use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\Gender;
use App\Modules\Hr\Enums\IdType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Support\BlindIndex;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Needs client_id, company_id and branch_id from the caller (the tenant tree is Platform's).
 *
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_number' => (string) fake()->unique()->numberBetween(1000, 99999),
            'name_ar' => 'موظف '.fake()->lastName(),
            'name_en' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '05'.fake()->numerify('########'),
            'id_type' => IdType::Iqama,
            'national_id' => (string) fake()->unique()->numerify('2#########'),
            'nationality' => 'EG',
            'gender' => Gender::Male,
            'birth_date' => fake()->dateTimeBetween('-50 years', '-22 years')->format('Y-m-d'),
            'hired_on' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'status' => EmployeeStatus::Active,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => EmployeeStatus::Draft]);
    }

    public function terminated(): static
    {
        return $this->state(fn (): array => ['status' => EmployeeStatus::Terminated, 'terminated_on' => now()->subMonth()->toDateString()]);
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Employee $employee): void {
            $employee->national_id_hash = BlindIndex::of($employee->national_id);
        });
    }
}
