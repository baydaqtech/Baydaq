<?php

namespace Database\Factories;

use App\Enums\PricingModel;
use App\Enums\ProjectServiceStatus;
use App\Models\Project;
use App\Models\ProjectService;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectService>
 */
class ProjectServiceFactory extends Factory
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
            'service_id' => Service::factory(),
            'status' => ProjectServiceStatus::Active,
            'plan' => 'Starter',
            'price' => '149.00',
            'pricing_model' => PricingModel::Monthly,
            'started_at' => now()->subMonth()->toDateString(),
        ];
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProjectServiceStatus::Paused]);
    }
}
