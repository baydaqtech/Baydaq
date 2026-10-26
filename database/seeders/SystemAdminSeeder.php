<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class SystemAdminSeeder extends Seeder
{
    /**
     * Create the owner account from ADMIN_NAME / ADMIN_EMAIL / ADMIN_PASSWORD.
     * Skipped when they are not set; an existing account is left as it is.
     */
    public function run(): void
    {
        ['name' => $name, 'email' => $email, 'password' => $password] = config('baydaq.admin');

        if (blank($email) || blank($password)) {
            $this->command?->warn('ADMIN_EMAIL / ADMIN_PASSWORD not set: no system admin created.');

            return;
        }

        $admin = User::query()->where('email', mb_strtolower($email))->first();

        if ($admin === null) {
            $admin = new User(['name' => $name ?: 'Baydaq Admin', 'email' => $email, 'password' => $password]);
            $admin->forceFill(['must_change_password' => true, 'email_verified_at' => now()])->save();
        }

        $admin->assignRole(Role::SystemAdmin);
    }
}
