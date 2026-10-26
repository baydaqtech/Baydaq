<?php

namespace Database\Factories;

use App\Enums\ProjectRelationship;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'client_id' => Client::factory(),
            'name_ar' => 'مشروع '.$name,
            'name_en' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description_ar' => 'وصف المشروع.',
            'description_en' => fake()->sentence(),
            'type' => ProjectType::ClientOwned,
            'status' => ProjectStatus::InDevelopment,
            'progress' => fake()->numberBetween(0, 100),
            'started_at' => now()->subMonths(2)->toDateString(),
        ];
    }

    /**
     * A project Baydaq runs as a technical partner for a share.
     */
    public function partnership(string $share = '35.00'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProjectType::PoweredByBaydaq,
            'relationship' => ProjectRelationship::Partnership,
            'partnership_share' => $share,
        ]);
    }

    /**
     * A Baydaq product run for the client.
     */
    public function product(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProjectType::PoweredByBaydaq,
            'relationship' => ProjectRelationship::Product,
        ]);
    }

    public function clientOwned(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProjectType::ClientOwned,
            'relationship' => null,
            'partnership_share' => null,
        ]);
    }

    public function live(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::Live,
            'progress' => 100,
            'launched_at' => now()->subWeek()->toDateString(),
        ]);
    }
}
