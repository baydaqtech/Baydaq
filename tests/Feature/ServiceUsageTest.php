<?php

namespace Tests\Feature;

use App\Actions\Services\IssueApiKey;
use App\Actions\Usage\RecordUsage;
use App\Actions\Usage\UsageSummary;
use App\Enums\Permission;
use App\Enums\PricingModel;
use App\Models\Client;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServiceUsageTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Project $project;

    private ProjectService $email;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-11-10');
        $this->client = Client::factory()->create();
        $this->project = Project::factory()->for($this->client)->create();
        $this->email = ProjectService::factory()->for($this->project)->create(['pricing_model' => PricingModel::PerUse, 'price' => '0.05']);
    }

    public function test_per_use_usage_adds_up_per_day_and_forecasts_the_month(): void
    {
        app(RecordUsage::class)->handle($this->email, 1000, '2026-11-01');
        app(RecordUsage::class)->handle($this->email, 500, '2026-11-10');
        app(RecordUsage::class)->handle($this->email, 500, '2026-11-10');
        app(RecordUsage::class)->handle($this->email, 9999, '2026-10-20');

        $summary = app(UsageSummary::class)->handle($this->email->fresh());

        $this->assertSame('2000.00', $summary['quantity']);
        $this->assertSame('100.00', $summary['cost']);              // 2000 × 0.05
        $this->assertSame('300.00', $summary['forecast']);          // 100 / 10 days × 30 days
        $this->assertSame('2026-11', $summary['month']);
        $this->assertCount(6, $summary['history']);
        $this->assertSame('9999.00', $summary['history'][4]['quantity']);
    }

    public function test_a_monthly_service_is_charged_once_per_month(): void
    {
        $push = ProjectService::factory()->for($this->project)->create(['pricing_model' => PricingModel::Monthly, 'price' => '149.00']);

        app(RecordUsage::class)->handle($push, 10, '2026-11-02');
        app(RecordUsage::class)->handle($push, 10, '2026-11-05');

        $this->assertSame('149.00', app(UsageSummary::class)->handle($push->fresh())['cost']);
        $this->assertSame('149.00', app(UsageSummary::class)->handle($push->fresh())['forecast']);
    }

    public function test_a_service_backend_reports_usage_with_its_key(): void
    {
        $this->postJson('/api/v1/usage', ['quantity' => 10])->assertUnauthorized();

        $key = app(IssueApiKey::class)->handle($this->email);
        $this->assertStringStartsWith("bsk_{$this->email->id}_", $key);

        $this->withHeader('X-Service-Key', $key)->postJson('/api/v1/usage', ['quantity' => 250])
            ->assertCreated()->assertJsonPath('quantity', '250.00')->assertJsonPath('cost', '12.50');

        $this->withHeader('X-Service-Key', 'bsk_'.$this->email->id.'_'.str_repeat('x', 40))->postJson('/api/v1/usage', ['quantity' => 1])->assertUnauthorized();

        $this->email->forceFill(['status' => 'paused'])->save();
        $this->withHeader('X-Service-Key', $key)->postJson('/api/v1/usage', ['quantity' => 1])->assertForbidden();
    }

    public function test_the_client_sees_usage_connection_and_milestones_on_the_project(): void
    {
        $user = User::factory()->client($this->client)->create();
        app(IssueApiKey::class)->handle($this->email);
        app(RecordUsage::class)->handle($this->email, 400, '2026-11-03');
        Milestone::factory()->done()->for($this->project)->create(['title' => 'Discovery']);
        Milestone::factory()->for($this->project)->create(['title' => 'Launch']);

        $this->actingAs($user)->get("/app/projects/{$this->project->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('services.0.connected', true)
                ->where('services.0.usage.quantity', '400.00')
                ->where('services.0.usage.cost', '20.00')
                ->has('project.milestones', 2)
                ->where('project.milestones.0.title', 'Discovery'));
    }

    public function test_staff_issue_keys_and_record_usage_by_hand(): void
    {
        $manager = User::factory()->admin(Permission::ProjectsManage)->create();

        $this->actingAs($manager)->post("/admin/projects/{$this->project->slug}/services/{$this->email->id}/key")->assertRedirect();
        $this->assertTrue($this->email->fresh()->hasApiKey());

        $this->actingAs($manager)->post("/admin/projects/{$this->project->slug}/services/{$this->email->id}/usage", ['quantity' => 120, 'date' => '2026-11-09'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('120.00', app(UsageSummary::class)->handle($this->email->fresh())['quantity']);

        $viewer = User::factory()->admin(Permission::ProjectsView)->create();
        $this->actingAs($viewer)->post("/admin/projects/{$this->project->slug}/services/{$this->email->id}/key")->assertForbidden();
    }
}
