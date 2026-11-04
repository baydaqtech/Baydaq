<?php

namespace App\Modules\Hr\Database\Factories;

use App\Modules\Hr\Models\Job;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Job>
 */
class JobFactory extends Factory
{
    protected $model = Job::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'name_ar' => 'وظيفة '.$name,
            'name_en' => $name,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(fn (Job $job) => $job->normalizeNames());
    }
}
