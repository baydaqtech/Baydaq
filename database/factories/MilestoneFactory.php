<?php

namespace Database\Factories;

use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Milestone>
 */
class MilestoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'due_date' => fake()->dateTimeBetween('-1 month', '+3 months')->format('Y-m-d'),
            'sort_order' => 0,
        ];
    }

    public function done(): static
    {
        return $this->state(fn (array $attributes) => ['done_at' => now()]);
    }
}
