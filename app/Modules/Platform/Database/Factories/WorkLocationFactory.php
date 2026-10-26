<?php

namespace App\Modules\Platform\Database\Factories;

use App\Models\Client;
use App\Modules\Platform\Models\WorkLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkLocation>
 */
class WorkLocationFactory extends Factory
{
    protected $model = WorkLocation::class;

    /**
     * Riyadh, Olaya.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name_ar' => 'مبنى '.fake()->streetName(),
            'name_en' => fake()->streetName().' Building',
            'latitude' => 24.6950000,
            'longitude' => 46.6850000,
            'radius_m' => 100,
        ];
    }
}
