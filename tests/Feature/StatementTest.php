<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Modules\Platform\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StatementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-11-10');
    }

    public function test_the_statement_joins_invoices_and_subscriptions(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->client($client)->create();
        $project = Project::factory()->for($client)->create();
        Invoice::factory()->sent()->for($client)->for($project)->create(['total' => '1000.00']);
        Invoice::factory()->overdue()->for($client)->create(['total' => '250.00']);
        Invoice::factory()->paid()->for($client)->create(['total' => '600.00', 'paid_at' => '2026-03-01']);
        Invoice::factory()->for($client)->create(['total' => '999.00']); // draft: hidden
        Subscription::factory()->seats(40)->create(['client_id' => $client->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);

        $this->actingAs($user)->get('/app/statement')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/Statement')
                ->has('invoices', 3)
                ->where('totals.due', '1250.00')
                ->where('totals.overdue', '250.00')
                ->where('totals.paid_this_year', '600.00')
                ->has('subscriptions', 1)
                ->where('subscriptions.0.total', '6000.00')
                ->where('subscriptions.0.days_left', 51));

        $this->actingAs(User::factory()->client()->create())->get('/app/statement')->assertInertia(fn (Assert $page) => $page->has('invoices', 0)->has('subscriptions', 0));
    }

    public function test_raising_seats_mid_term_drafts_a_prorated_invoice_and_a_period_can_be_invoiced(): void
    {
        $client = Client::factory()->create();
        $manager = User::factory()->admin(Permission::ClientsManage, Permission::InvoicesManage)->create();
        $subscription = Subscription::factory()->seats(50)->create(['client_id' => $client->id, 'unit_price' => '150.00', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);

        $this->actingAs($manager)->put("/admin/clients/{$client->id}/subscriptions/{$subscription->id}", [
            'module' => 'hr', 'status' => 'active', 'quantity' => 60, 'unit_price' => '150.00', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ])->assertRedirect();

        $prorated = Invoice::query()->where('client_id', $client->id)->firstOrFail();
        // 10 seats × 150 × 52 days left / 365 days = 21.37 per seat.
        $this->assertSame('21.37', (string) $prorated->items()->first()->unit_price);
        $this->assertSame('10.00', (string) $prorated->items()->first()->quantity);
        $this->assertSame('draft', $prorated->status->value);

        $this->actingAs($manager)->post("/admin/clients/{$client->id}/subscriptions/{$subscription->id}/invoice")->assertRedirect();
        $period = Invoice::query()->where('client_id', $client->id)->latest('id')->firstOrFail();
        $this->assertSame('9000.00', (string) $period->subtotal);
        $this->assertStringContainsString('2026-01-01 → 2026-12-31', $period->items()->first()->description);
    }
}
