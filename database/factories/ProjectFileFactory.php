<?php

namespace Database\Factories;

use App\Enums\FileCategory;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectFile>
 */
class ProjectFileFactory extends Factory
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
            'title' => fake()->words(3, true),
            'url' => 'https://drive.google.com/file/d/'.fake()->regexify('[A-Za-z0-9]{20}'),
            'category' => fake()->randomElement(FileCategory::cases()),
        ];
    }
}
