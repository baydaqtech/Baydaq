<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\ClientRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientRequest>
 */
class ClientRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => RequestType::Partnership,
            'name' => fake()->name(),
            'company' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('05########'),
            'message' => fake()->paragraph(),
            'status' => RequestStatus::New,
            'locale' => 'ar',
        ];
    }

    /**
     * @param  list<string>  $serviceKeys
     */
    public function forServices(array $serviceKeys = ['email', 'sms']): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => RequestType::Service,
            'services' => $serviceKeys,
        ]);
    }
}
