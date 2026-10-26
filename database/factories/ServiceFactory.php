<?php

namespace Database\Factories;

use App\Enums\PricingModel;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->unique()->lexify('service-????');

        return [
            'key' => $key,
            'name_ar' => 'خدمة '.$key,
            'name_en' => ucfirst($key),
            'description_ar' => 'وصف الخدمة.',
            'description_en' => fake()->sentence(),
            'icon' => 'box',
            'is_active' => true,
            'sort_order' => 0,
            'default_price' => '99.00',
            'pricing_model' => PricingModel::Monthly,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
