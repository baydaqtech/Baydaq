<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Models\Client;
use App\Models\User;
use App\Modules\Platform\Models\Access\AccessRole;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientSuiteAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->manager = User::factory()->admin(Permission::ClientsManage)->create();
        $this->client = Client::factory()->create();
    }

    public function test_staff_record_a_subscription_for_a_client(): void
    {
        $this->actingAs($this->manager)
            ->post("/admin/clients/{$this->client->id}/subscriptions", [
                'module' => 'hr', 'status' => 'active', 'quantity' => 25, 'unit_price' => '150.00',
                'starts_on' => '2026-11-01', 'ends_on' => '2027-10-31',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $subscription = Subscription::forClient($this->client)->sole();
        $this->assertSame(25, $subscription->quantity);
        $this->assertSame('seat', $subscription->billing_unit->value);

        $this->actingAs($this->manager)
            ->put("/admin/clients/{$this->client->id}/subscriptions/{$subscription->id}", [
                'module' => 'hr', 'status' => 'active', 'quantity' => 40, 'unit_price' => '150.00',
                'starts_on' => '2026-11-01', 'ends_on' => '2027-10-31',
            ])
            ->assertRedirect();
        $this->assertSame(40, $subscription->fresh()->quantity);
    }

    public function test_a_subscription_cannot_be_edited_through_another_client(): void
    {
        $subscription = Subscription::factory()->create(['client_id' => $this->client->id]);
        $other = Client::factory()->create();

        $this->actingAs($this->manager)
            ->put("/admin/clients/{$other->id}/subscriptions/{$subscription->id}", [
                'module' => 'hr', 'status' => 'active', 'quantity' => 1, 'unit_price' => '1', 'starts_on' => '2026-01-01',
            ])
            ->assertNotFound();
    }

    public function test_provisioning_makes_the_client_accounts_owners_and_opens_the_suite(): void
    {
        $owner = User::factory()->client($this->client)->create();

        $this->actingAs($this->manager)->post("/admin/clients/{$this->client->id}/suite")->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1, AccessRole::forClient($this->client)->where('key', 'owner')->count());
        $this->assertSame(1, Company::forClient($this->client)->count());

        $this->actingAs($owner)->get('/suite')->assertRedirect();
        $this->actingAs($owner)->get('/suite/all')->assertOk();

        $this->actingAs($this->manager)
            ->get("/admin/clients/{$this->client->id}")
            ->assertInertia(fn (Assert $page) => $page->where('suite.provisioned', true)->has('subscriptions', 0));
    }

    public function test_provisioning_needs_a_client_account_first(): void
    {
        $this->actingAs($this->manager)->post("/admin/clients/{$this->client->id}/suite")->assertSessionHasErrors('suite');
    }

    public function test_a_viewer_cannot_change_subscriptions(): void
    {
        $viewer = User::factory()->admin(Permission::ClientsView)->create();

        $this->actingAs($viewer)->post("/admin/clients/{$this->client->id}/subscriptions", [])->assertForbidden();
        $this->actingAs($viewer)->post("/admin/clients/{$this->client->id}/suite")->assertForbidden();
    }
}
