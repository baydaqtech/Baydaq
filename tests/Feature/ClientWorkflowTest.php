<?php

namespace Tests\Feature;

use App\Actions\Usage\RecordUsage;
use App\Actions\Usage\UsageSummary;
use App\Enums\ChangeRequestStatus;
use App\Enums\InvoiceStatus;
use App\Enums\Permission;
use App\Enums\PricingModel;
use App\Models\ChangeRequest;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectService;
use App\Models\User;
use App\Notifications\ChangeRequestUpdated;
use App\Notifications\SpendCapReached;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The client-facing extras: spend caps and delivery health (B2), the release timeline (B3) and
 * change requests with quotes (B5).
 */
class ClientWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private User $clientUser;

    private User $manager;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-11-10');
        Notification::fake();
        $this->client = Client::factory()->create();
        $this->clientUser = User::factory()->client($this->client)->create();
        $this->manager = User::factory()->admin(Permission::ProjectsManage, Permission::InvoicesManage)->create();
        $this->project = Project::factory()->for($this->client)->create();
    }

    public function test_the_client_is_warned_once_at_80_and_once_at_100_percent_of_the_cap(): void
    {
        $sms = ProjectService::factory()->for($this->project)->create(['pricing_model' => PricingModel::PerUse, 'price' => '1.00']);
        $this->actingAs($this->clientUser)->put("/app/projects/{$this->project->slug}/services/{$sms->id}/cap", ['monthly_cap' => '100'])->assertRedirect();

        app(RecordUsage::class)->handle($sms->fresh(), 50);
        Notification::assertNothingSent();

        app(RecordUsage::class)->handle($sms->fresh(), 35);
        app(RecordUsage::class)->handle($sms->fresh(), 5);
        Notification::assertSentToTimes($this->clientUser, SpendCapReached::class, 1);
        Notification::assertSentTo($this->manager, SpendCapReached::class, fn (SpendCapReached $notification) => $notification->level === 80);

        app(RecordUsage::class)->handle($sms->fresh(), 20);
        Notification::assertSentToTimes($this->clientUser, SpendCapReached::class, 2);
        Notification::assertSentTo($this->clientUser, SpendCapReached::class, fn (SpendCapReached $notification) => $notification->level === 100);
    }

    public function test_another_clients_service_cap_cannot_be_changed(): void
    {
        $other = ProjectService::factory()->create(['pricing_model' => PricingModel::PerUse]);

        $this->actingAs($this->clientUser)->put("/app/projects/{$other->project->slug}/services/{$other->id}/cap", ['monthly_cap' => '1'])->assertForbidden();
    }

    public function test_delivery_counters_add_up_into_monthly_rates(): void
    {
        $whatsapp = ProjectService::factory()->for($this->project)->create(['pricing_model' => PricingModel::PerUse, 'price' => '0.10']);
        app(RecordUsage::class)->handle($whatsapp, 100, null, 'api', ['sent' => 100, 'delivered' => 90, 'read' => 60, 'bounced' => 4]);
        app(RecordUsage::class)->handle($whatsapp, 100, null, 'api', ['sent' => 100, 'delivered' => 100, 'read' => 80, 'bounced' => 0]);

        $health = app(UsageSummary::class)->handle($whatsapp->fresh())['health'];

        $this->assertSame(200, $health['sent']);
        $this->assertSame(95.0, $health['delivered_rate']);
        $this->assertSame(70.0, $health['read_rate']);
        $this->assertSame(2.0, $health['bounce_rate']);
    }

    public function test_the_team_publishes_releases_and_the_client_sees_the_timeline(): void
    {
        $this->actingAs($this->manager)->put("/admin/projects/{$this->project->slug}/releases", ['items' => [
            ['version' => '1.0.0 (12)', 'platform' => 'ios', 'channel' => 'testflight', 'url' => 'https://testflight.apple.com/join/abc', 'released_at' => '2026-11-01'],
            ['version' => '1.0.0', 'platform' => 'android', 'channel' => 'production', 'released_at' => '2026-11-08'],
        ]])->assertSessionHasNoErrors();

        $this->actingAs($this->clientUser)->get("/app/projects/{$this->project->slug}?tab=releases")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('project.releases', 2)
            ->where('project.releases.0.channel', 'production')
            ->where('project.releases.1.url', 'https://testflight.apple.com/join/abc'));
    }

    public function test_a_change_request_is_quoted_approved_and_drafts_an_invoice(): void
    {
        $this->actingAs($this->clientUser)->post("/app/projects/{$this->project->slug}/changes", ['title' => 'Add Apple Pay', 'description' => 'At checkout.'])->assertRedirect();
        $change = ChangeRequest::query()->sole();
        Notification::assertSentTo($this->manager, ChangeRequestUpdated::class);

        $this->actingAs($this->clientUser)->post("/app/projects/{$this->project->slug}/changes/{$change->id}/decide", ['decision' => 'approve'])->assertSessionHasErrors('status');

        $this->actingAs($this->manager)->post("/admin/projects/{$this->project->slug}/changes/{$change->id}/quote", ['quote_amount' => '2500', 'quote_days' => 7])->assertSessionHasNoErrors();
        Notification::assertSentTo($this->clientUser, ChangeRequestUpdated::class);

        $this->actingAs($this->clientUser)->post("/app/projects/{$this->project->slug}/changes/{$change->id}/decide", ['decision' => 'approve'])->assertSessionHasNoErrors();

        $change->refresh();
        $this->assertSame(ChangeRequestStatus::Approved, $change->status);
        $this->assertSame($this->clientUser->id, $change->decided_by);
        $this->assertSame(InvoiceStatus::Draft, $change->invoice->status);
        $this->assertSame('2500.00', (string) $change->invoice->subtotal);
        $this->assertSame($this->project->id, $change->invoice->project_id);
    }

    public function test_a_client_cannot_touch_another_clients_change_request(): void
    {
        $otherProject = Project::factory()->create();
        $change = $otherProject->changeRequests()->create(['title' => 'X', 'description' => 'Y']);

        $this->actingAs($this->clientUser)->post("/app/projects/{$otherProject->slug}/changes/{$change->id}/withdraw")->assertForbidden();
        $this->actingAs($this->clientUser)->post("/app/projects/{$this->project->slug}/changes/{$change->id}/withdraw")->assertNotFound();
    }
}
