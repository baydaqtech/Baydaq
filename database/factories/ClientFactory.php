<?php

namespace Database\Factories;

use App\Enums\ClientStatus;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = fake()->company();

        return [
            'name_ar' => 'شركة '.$company,
            'name_en' => $company,
            'contact_name' => fake()->name(),
            'contact_email' => fake()->unique()->safeEmail(),
            'contact_phone' => fake()->numerify('05########'),
            'status' => ClientStatus::Active,
        ];
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ClientStatus::Paused]);
    }
}
