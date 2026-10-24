<?php

namespace App\Modules\Platform\Database\Factories;

use App\Models\Client;
use App\Modules\Platform\Models\Access\AccessRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessRole>
 */
class AccessRoleFactory extends Factory
{
    protected $model = AccessRole::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name_ar' => fake()->jobTitle(),
            'name_en' => fake()->jobTitle(),
        ];
    }

    /**
     * A role holding the given permission keys.
     *
     * @param  list<string>  $keys
     */
    public function granting(array $keys): static
    {
        return $this->afterCreating(function (AccessRole $role) use ($keys): void {
            $role->permissions()->createMany(array_map(fn (string $key): array => ['permission' => $key], $keys));
        });
    }
}
