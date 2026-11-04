<?php

namespace App\Modules\Platform\Database\Factories;

use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => strtoupper(fake()->unique()->bothify('BR-###')),
            'name_ar' => 'فرع '.fake()->city(),
            'name_en' => fake()->city().' Branch',
            'city_ar' => 'الرياض',
            'city_en' => 'Riyadh',
        ];
    }

    public function headOffice(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_head_office' => true,
            'code' => 'HQ',
            'name_ar' => 'المكتب الرئيسي',
            'name_en' => 'Head Office',
        ]);
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Branch $branch): void {
            if (empty($branch->client_id)) {
                $branch->client_id = $branch->company->client_id;
            }
        });
    }
}
