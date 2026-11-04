<?php

namespace Tests\Feature\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\Permission;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->manager = User::factory()->admin(Permission::InvoicesManage)->create();
    }

    public function test_totals_include_15_percent_vat_and_numbers_run_per_year(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->manager)->post('/admin/invoices', $this->form($client, [
            ['description' => 'Design', 'quantity' => '2', 'unit_price' => '1250.50'],
            ['description' => 'Hosting', 'quantity' => '1', 'unit_price' => '99.99'],
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $invoice = Invoice::sole();
        $this->assertSame('INV-2026-0001', $invoice->number);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame('2600.99', $invoice->subtotal);
        $this->assertSame('390.15', $invoice->vat);
        $this->assertSame('2991.14', $invoice->total);
        $this->assertSame(['2501.00', '99.99'], $invoice->items->pluck('total')->all());

        $this->actingAs($this->manager)->post('/admin/invoices', $this->form($client));
        $this->assertSame('INV-2026-0002', Invoice::latest('id')->first()->number);
    }

    public function test_the_project_must_belong_to_the_client(): void
    {
        $client = Client::factory()->create();
        $otherProject = Project::factory()->create();

        $this->actingAs($this->manager)
            ->post('/admin/invoices', [...$this->form($client), 'project_id' => $otherProject->id])
            ->assertSessionHasErrors('project_id');
    }

    public function test_sending_saves_the_company_details_and_locks_editing(): void
    {
        Setting::store(['company_name_en' => 'Baydaq Tech', 'iban' => 'SA00 0000']);
        $invoice = Invoice::factory()->create();

        $this->actingAs($this->manager)->post("/admin/invoices/{$invoice->id}/send")->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Sent, $invoice->status);
        $this->assertNotNull($invoice->sent_at);
        $this->assertSame('Baydaq Tech', $invoice->issuer['company_name_en']);

        $this->actingAs($this->manager)->get("/admin/invoices/{$invoice->id}/edit")->assertForbidden();
        $this->actingAs($this->manager)->put("/admin/invoices/{$invoice->id}", $this->form($invoice->client))->assertForbidden();
    }

    public function test_paid_and_void_follow_the_allowed_order(): void
    {
        $draft = Invoice::factory()->create();
        $this->actingAs($this->manager)->post("/admin/invoices/{$draft->id}/pay")->assertSessionHasErrors('status');
        $this->assertSame(InvoiceStatus::Draft, $draft->fresh()->status);

        $sent = Invoice::factory()->sent()->create();
        $this->actingAs($this->manager)->post("/admin/invoices/{$sent->id}/pay")->assertSessionHasNoErrors();
        $this->assertSame(InvoiceStatus::Paid, $sent->fresh()->status);
        $this->assertNotNull($sent->fresh()->paid_at);

        $this->actingAs($this->manager)->post("/admin/invoices/{$sent->id}/void")->assertSessionHasErrors('status');
        $this->actingAs($this->manager)->post("/admin/invoices/{$draft->id}/void")->assertSessionHasNoErrors();
        $this->assertSame(InvoiceStatus::Void, $draft->fresh()->status);
    }

    public function test_viewers_cannot_change_invoices(): void
    {
        $invoice = Invoice::factory()->create();
        $viewer = User::factory()->admin(Permission::InvoicesView)->create();

        $this->actingAs($viewer)->get("/admin/invoices/{$invoice->id}")->assertOk();
        $this->actingAs($viewer)->post("/admin/invoices/{$invoice->id}/send")->assertForbidden();
    }

    public function test_sent_invoices_past_their_due_date_become_overdue(): void
    {
        $late = Invoice::factory()->sent()->create(['due_date' => today()->subDay()]);
        $onTime = Invoice::factory()->sent()->create(['due_date' => today()]);
        $draft = Invoice::factory()->create(['due_date' => today()->subDays(5)]);

        $this->artisan('invoices:mark-overdue')->assertSuccessful();

        $this->assertSame(InvoiceStatus::Overdue, $late->fresh()->status);
        $this->assertSame(InvoiceStatus::Sent, $onTime->fresh()->status);
        $this->assertSame(InvoiceStatus::Draft, $draft->fresh()->status);
    }

    /**
     * @param  list<array<string, string>>|null  $items
     * @return array<string, mixed>
     */
    private function form(Client $client, ?array $items = null): array
    {
        return [
            'client_id' => $client->id,
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-15',
            'items' => $items ?? [['description' => 'Work', 'quantity' => '1', 'unit_price' => '100']],
        ];
    }
}
