<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectResource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectResource>
 */
class ProjectResourceFactory extends Factory
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
            'name' => fake()->name(),
            'role' => fake()->jobTitle(),
        ];
    }
}
