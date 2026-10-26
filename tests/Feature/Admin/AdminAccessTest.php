<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, array{string, Permission}>
     */
    public static function areas(): array
    {
        return [
            'clients' => ['/admin/clients', Permission::ClientsView],
            'projects' => ['/admin/projects', Permission::ProjectsView],
            'invoices' => ['/admin/invoices', Permission::InvoicesView],
            'requests' => ['/admin/requests', Permission::RequestsManage],
            'services' => ['/admin/services', Permission::ServicesManage],
            'products' => ['/admin/products', Permission::ServicesManage],
            'team' => ['/admin/team', Permission::AdminsManage],
            'settings' => ['/admin/settings', Permission::SettingsManage],
        ];
    }

    /**
     * Areas with separate view and manage permissions.
     *
     * @return array<string, array{string, Permission, Permission}>
     */
    public static function areasWithViewers(): array
    {
        return [
            'clients' => ['/admin/clients', Permission::ClientsView, Permission::ClientsManage],
            'projects' => ['/admin/projects', Permission::ProjectsView, Permission::ProjectsManage],
            'invoices' => ['/admin/invoices', Permission::InvoicesView, Permission::InvoicesManage],
        ];
    }

    #[DataProvider('areas')]
    public function test_each_area_needs_its_permission(string $path, Permission $permission): void
    {
        $this->actingAs(User::factory()->admin()->create())->get($path)->assertForbidden();
        $this->actingAs(User::factory()->admin($permission)->create())->get($path)->assertOk();
    }

    #[DataProvider('areas')]
    public function test_the_system_admin_opens_every_area(string $path): void
    {
        $this->actingAs(User::factory()->systemAdmin()->create())->get($path)->assertOk();
    }

    #[DataProvider('areasWithViewers')]
    public function test_view_permission_does_not_allow_changes(string $path, Permission $view, Permission $manage): void
    {
        $viewer = User::factory()->admin($view)->create();
        $this->actingAs($viewer)->get("{$path}/create")->assertForbidden();

        $manager = User::factory()->admin($manage)->create();
        $this->actingAs($manager)->get($path)->assertOk();
        $this->actingAs($manager)->get("{$path}/create")->assertOk();
    }

    public function test_every_team_member_sees_the_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
    }

    public function test_clients_cannot_reach_any_admin_page(): void
    {
        $client = User::factory()->client()->create();

        foreach (array_column(self::areas(), 0) as $path) {
            $this->actingAs($client)->get($path)->assertForbidden();
        }
    }
}
