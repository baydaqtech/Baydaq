<?php

namespace App\Modules\Platform\Database\Factories;

use App\Models\Client;
use App\Modules\Platform\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'client_id' => Client::factory(),
            'slug' => Str::slug($name),
            'name_ar' => 'مجموعة '.fake()->lastName(),
            'name_en' => $name,
        ];
    }
}
