<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_roles_permissions_catalog_and_products(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertEqualsCanonicalizing(array_column(Role::cases(), 'value'), RoleModel::pluck('name')->all());
        $this->assertEqualsCanonicalizing(Permission::values(), PermissionModel::pluck('name')->all());
        $this->assertSame(['email', 'otp', 'sms', 'whatsapp', 'push', 'payments'], Service::listed()->pluck('key')->all());
        $this->assertSame(11, Product::count());
    }

    public function test_the_system_admin_comes_from_the_environment_and_must_change_password(): void
    {
        config(['baydaq.admin' => ['name' => 'Owner', 'email' => 'Owner@Baydaq.test', 'password' => 'first-pass-1']]);

        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', 'owner@baydaq.test')->firstOrFail();
        $this->assertTrue($owner->isSystemAdmin());
        $this->assertTrue($owner->must_change_password);
        $this->assertTrue(Hash::check('first-pass-1', $owner->password));
    }

    public function test_no_system_admin_is_created_without_credentials(): void
    {
        config(['baydaq.admin' => ['name' => null, 'email' => null, 'password' => null]]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, User::role(Role::SystemAdmin)->count());
    }

    public function test_running_it_again_keeps_admin_edits(): void
    {
        config(['baydaq.admin' => ['name' => 'Owner', 'email' => 'owner@baydaq.test', 'password' => 'first-pass-1']]);
        $this->seed(DatabaseSeeder::class);

        Product::where('slug', 'hr-system')->update(['name_en' => 'Kawader HR']);
        Service::where('key', 'sms')->update(['default_price' => '0.12']);
        User::where('email', 'owner@baydaq.test')->first()->forceFill(['password' => 'changed-pass-2', 'must_change_password' => false])->save();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame('Kawader HR', Product::where('slug', 'hr-system')->value('name_en'));
        $this->assertSame('0.12', Service::where('key', 'sms')->first()->default_price);
        $this->assertSame(11, Product::count());
        $owner = User::where('email', 'owner@baydaq.test')->first();
        $this->assertFalse($owner->must_change_password);
        $this->assertTrue(Hash::check('changed-pass-2', $owner->password));
    }
}
