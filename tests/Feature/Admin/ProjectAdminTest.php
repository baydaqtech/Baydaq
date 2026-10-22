<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\ProjectRelationship;
use App\Models\Client;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectService;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->manager = User::factory()->admin(Permission::ProjectsManage)->create();
    }

    public function test_a_partnership_project_is_created_with_a_slug_and_logo(): void
    {
        Storage::fake('public');
        $client = Client::factory()->create();

        $this->actingAs($this->manager)->post('/admin/projects', [
            ...$this->form($client),
            'type' => 'powered_by_baydaq',
            'relationship' => 'partnership',
            'partnership_share' => '35',
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/projects/delivery-app');

        $project = Project::sole();
        $this->assertSame(ProjectRelationship::Partnership, $project->relationship);
        $this->assertSame('35.00', $project->partnership_share);
        Storage::disk('public')->assertExists($project->logo);
    }

    public function test_a_partnership_needs_its_share_and_client_projects_drop_it(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->manager)
            ->post('/admin/projects', [...$this->form($client), 'type' => 'powered_by_baydaq', 'relationship' => 'partnership'])
            ->assertSessionHasErrors('partnership_share');

        $project = Project::factory()->for($client)->partnership()->create();

        $this->actingAs($this->manager)
            ->put("/admin/projects/{$project->slug}", [...$this->form($client), 'slug' => $project->slug, 'type' => 'client_owned', 'relationship' => 'partnership', 'partnership_share' => '20'])
            ->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertNull($project->relationship);
        $this->assertNull($project->partnership_share);
    }

    public function test_milestones_are_saved_as_one_list(): void
    {
        $project = Project::factory()->create();
        $keep = Milestone::factory()->for($project)->create(['title' => 'Design']);
        $drop = Milestone::factory()->for($project)->create(['title' => 'Old']);
        $other = Milestone::factory()->create();

        $this->actingAs($this->manager)->put("/admin/projects/{$project->slug}/milestones", [
            'items' => [
                ['id' => $keep->id, 'title' => 'Design done', 'done' => true],
                ['title' => 'Launch', 'due_date' => '2026-12-01', 'done' => false],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Design done', 'Launch'], $project->milestones()->pluck('title')->all());
        $this->assertNotNull($keep->fresh()->done_at);
        $this->assertModelMissing($drop);
        $this->assertModelExists($other);
    }

    public function test_a_service_is_turned_on_and_paused(): void
    {
        $project = Project::factory()->create();
        $service = Service::factory()->create();

        $this->actingAs($this->manager)->post("/admin/projects/{$project->slug}/services", [
            'service_id' => $service->id, 'plan' => 'Growth', 'price' => '250', 'pricing_model' => 'monthly',
        ])->assertSessionHasNoErrors();

        $projectService = ProjectService::sole();
        $this->assertSame('active', $projectService->status->value);

        $this->actingAs($this->manager)
            ->post("/admin/projects/{$project->slug}/services", ['service_id' => $service->id, 'pricing_model' => 'monthly'])
            ->assertSessionHasErrors('service_id');

        $this->actingAs($this->manager)->put("/admin/projects/{$project->slug}/services/{$projectService->id}", [
            'plan' => 'Growth', 'price' => '250', 'pricing_model' => 'monthly', 'status' => 'paused',
        ])->assertSessionHasNoErrors();

        $this->assertSame('paused', $projectService->fresh()->status->value);
    }

    public function test_the_project_page_never_exposes_api_keys(): void
    {
        $projectService = ProjectService::factory()->create();
        $projectService->forceFill(['api_key' => 'bdq_live_secret'])->save();

        $this->actingAs($this->manager)
            ->get("/admin/projects/{$projectService->project->slug}")
            ->assertOk()
            ->assertDontSee('bdq_live_secret');
    }

    /**
     * @return array<string, mixed>
     */
    private function form(Client $client): array
    {
        return [
            'client_id' => $client->id,
            'name_ar' => 'تطبيق التوصيل',
            'name_en' => 'Delivery App',
            'slug' => '',
            'type' => 'client_owned',
            'status' => 'in_development',
            'progress' => 40,
        ];
    }
}
