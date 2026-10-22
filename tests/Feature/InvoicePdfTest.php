<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_client_downloads_their_sent_invoice(): void
    {
        $client = Client::factory()->create(['name_ar' => 'شركة المثال']);
        $invoice = $this->invoiceFor($client, 'sent');

        $response = $this->actingAs(User::factory()->client($client)->create())
            ->get(route('portal.invoices.pdf', $invoice))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="'.$invoice->number.'.pdf"');

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_clients_cannot_download_another_clients_invoice(): void
    {
        $invoice = $this->invoiceFor(Client::factory()->create(), 'sent');

        $this->actingAs(User::factory()->client()->create())
            ->get(route('portal.invoices.pdf', $invoice))
            ->assertForbidden();
    }

    public function test_clients_do_not_see_draft_or_void_invoices(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->client($client)->create();

        $draft = $this->invoiceFor($client);
        $void = $this->invoiceFor($client, 'sent');
        $void->forceFill(['status' => 'void'])->save();

        $this->actingAs($user)->get(route('portal.invoices.pdf', $draft))->assertForbidden();
        $this->actingAs($user)->get(route('portal.invoices.pdf', $void))->assertForbidden();
    }

    public function test_the_team_needs_invoice_access_to_download(): void
    {
        $invoice = $this->invoiceFor(Client::factory()->create());

        $this->actingAs(User::factory()->admin(Permission::InvoicesView)->create())
            ->get(route('admin.invoices.pdf', $invoice))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs(User::factory()->admin(Permission::ClientsView)->create())
            ->get(route('admin.invoices.pdf', $invoice))
            ->assertForbidden();
    }

    private function invoiceFor(Client $client, ?string $state = null): Invoice
    {
        $factory = Invoice::factory()->for($client);
        $invoice = ($state ? $factory->{$state}() : $factory)->create([
            'issuer' => ['company_name_ar' => 'بيدق تك', 'company_name_en' => 'Baydaq Tech', 'iban' => 'SA00 0000 0000 0000 0000 0000'],
        ]);
        InvoiceItem::factory()->for($invoice)->create(['description' => 'تطوير التطبيق']);

        return $invoice;
    }
}
