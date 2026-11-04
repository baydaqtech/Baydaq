<?php

namespace App\Modules\Platform\Database\Factories;

use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'organization_id' => Organization::factory(),
            'slug' => Str::slug($name),
            'name_ar' => 'شركة '.fake()->lastName(),
            'name_en' => $name,
            'cr_number' => (string) fake()->numerify('10########'),
            'vat_number' => (string) fake()->numerify('3##############'),
        ];
    }

    public function configure(): static
    {
        // A company always belongs to the same client as its organization.
        return $this->afterMaking(function (Company $company): void {
            if (empty($company->client_id)) {
                $company->client_id = $company->organization->client_id;
            }
        });
    }
}
