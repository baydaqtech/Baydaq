<?php

namespace Database\Factories;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * The owner, who passes every permission check.
     */
    public function systemAdmin(): static
    {
        return $this->withRole(Role::SystemAdmin);
    }

    /**
     * A team member with only the given permissions.
     */
    public function admin(Permission ...$permissions): static
    {
        return $this->withRole(Role::Admin)->afterCreating(function (User $user) use ($permissions): void {
            foreach ($permissions as $permission) {
                PermissionModel::findOrCreate($permission->value);
            }

            $user->givePermissionTo(array_map(fn (Permission $permission): string => $permission->value, $permissions));
        });
    }

    /**
     * A partner or customer account, linked to a client company.
     */
    public function client(?Client $client = null): static
    {
        return $this->withRole(Role::Client)->state(fn (array $attributes) => [
            'client_id' => $client ?? Client::factory(),
        ]);
    }

    /**
     * An account that has to pick a new password before using the platform.
     */
    public function mustChangePassword(): static
    {
        return $this->state(fn (array $attributes) => [
            'must_change_password' => true,
        ]);
    }

    private function withRole(Role $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            RoleModel::findOrCreate($role->value);
            $user->assignRole($role);
        });
    }
}
