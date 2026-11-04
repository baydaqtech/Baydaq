<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->client = Client::factory()->create();
        $this->user = User::factory()->client($this->client)->create();
    }

    public function test_the_home_lists_only_the_clients_own_projects(): void
    {
        $mine = Project::factory()->for($this->client)->partnership()->create();
        Project::factory()->create();

        $this->actingAs($this->user)->get('/app')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/Home')
                ->has('projects', 1)
                ->where('projects.0.slug', $mine->slug)
                ->where('projects.0.type', 'powered_by_baydaq'));
    }

    public function test_billing_counts_issued_invoices_and_hides_drafts(): void
    {
        Invoice::factory()->for($this->client)->sent()->create(['total' => '115.00']);
        Invoice::factory()->for($this->client)->overdue()->create(['total' => '230.00']);
        Invoice::factory()->for($this->client)->paid()->create(['total' => '50.00']);
        Invoice::factory()->for($this->client)->create(['total' => '999.00']);
        Invoice::factory()->sent()->create(['total' => '777.00']);

        $this->actingAs($this->user)->get('/app')
            ->assertInertia(fn (Assert $page) => $page
                ->has('invoices', 3)
                ->where('billing.due', '345.00')
                ->where('billing.overdue', '230.00'));
    }

    public function test_a_client_opens_their_project_with_its_services_and_invoices(): void
    {
        $project = Project::factory()->for($this->client)->create();
        $projectService = ProjectService::factory()->for($project)->create();
        $projectService->forceFill(['api_key' => 'bdq_live_secret', 'notes' => 'internal note'])->save();
        Invoice::factory()->for($this->client)->for($project)->sent()->create();
        Invoice::factory()->for($this->client)->for($project)->create();

        $this->actingAs($this->user)->get("/app/projects/{$project->slug}?tab=services")
            ->assertOk()
            ->assertDontSee('bdq_live_secret')
            ->assertDontSee('internal note')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/Project')
                ->where('tab', 'services')
                ->has('services', 1)
                ->has('invoices', 1)
                ->missing('services.0.api_key'));
    }

    public function test_a_client_cannot_open_another_clients_project(): void
    {
        $theirs = Project::factory()->create();

        $this->actingAs($this->user)->get("/app/projects/{$theirs->slug}")->assertForbidden();
    }

    public function test_an_account_without_a_client_sees_nothing(): void
    {
        $orphan = User::factory()->client()->create();
        $orphan->forceFill(['client_id' => null])->save();
        $project = Project::factory()->create();

        $this->actingAs($orphan)->get('/app')
            ->assertInertia(fn (Assert $page) => $page->has('projects', 0)->has('invoices', 0));
        $this->actingAs($orphan)->get("/app/projects/{$project->slug}")->assertForbidden();
    }

    public function test_the_team_uses_the_admin_not_the_portal(): void
    {
        $project = Project::factory()->create();
        $admin = User::factory()->admin(Permission::ProjectsView)->create();

        $this->actingAs($admin)->get("/app/projects/{$project->slug}")->assertForbidden();
    }
}
