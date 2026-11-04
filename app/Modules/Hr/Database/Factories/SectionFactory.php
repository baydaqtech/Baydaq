<?php

namespace App\Modules\Hr\Database\Factories;

use App\Modules\Hr\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    protected $model = Section::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'name_ar' => 'قسم '.$name,
            'name_en' => $name,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(fn (Section $section) => $section->normalizeNames());
    }
}
