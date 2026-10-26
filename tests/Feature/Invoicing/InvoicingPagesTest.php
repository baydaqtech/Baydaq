<?php

namespace Tests\Feature\Invoicing;

use App\Enums\Permission;
use App\Models\Invoice as CoreInvoice;
use App\Models\Setting;
use App\Models\User;
use App\Modules\Invoicing\Actions\SaveInvoice;
use App\Modules\Invoicing\Enums\InvoicingFeature;
use App\Modules\Invoicing\Models\Customer;
use App\Modules\Invoicing\Models\EinvoiceUnit;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Message;
use App\Modules\Platform\Models\Payment;
use App\Modules\Platform\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\Concerns\FakesZatca;
use Tests\TestCase;

class InvoicingPagesTest extends TestCase
{
    use BuildsSuite, FakesZatca, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-11-01 10:00:00');
        Subscription::factory()->module(Module::Invoicing)->create(['client_id' => $this->client->id, 'quantity' => 1]);
        Company::query()->whereKey($this->company->id)->update(['vat_number' => '300000000000003', 'cr_number' => '1010101010', 'street' => 'King Fahd', 'building_number' => '1234', 'district' => 'Olaya', 'city' => 'Riyadh', 'postal_code' => '12211']);
    }

    private function url(string $path, string $scope = 'all'): string
    {
        return "/suite/{$scope}/invoicing/".ltrim($path, '/');
    }

    public function test_the_owner_creates_a_customer_a_draft_and_issues_it(): void
    {
        $this->fakeZatcaAccepting();
        $this->readyUnit($this->company->id, $this->client->id);

        $this->actingAs($this->owner)->post($this->url('customers'), ['name_ar' => 'عميل', 'name_en' => 'Buyer', 'vat_number' => '311111111111113', 'street' => 'A', 'building_number' => '1111', 'district' => 'B', 'city' => 'Riyadh', 'postal_code' => '12345', 'country' => 'SA'])->assertSessionHasNoErrors();
        $customer = Customer::query()->sole();

        $this->actingAs($this->owner)->get($this->url('invoices/create'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Invoicing/Invoices/Form')->has('companies', 1)->has('customers', 1));

        $this->actingAs($this->owner)->post($this->url('invoices'), [
            'company_id' => $this->company->id, 'customer_id' => $customer->id, 'type' => 'standard', 'kind' => 'invoice', 'issue_date' => '2026-11-01', 'payment_means' => '42',
            'lines' => [['description' => 'Consulting', 'quantity' => 10, 'unit_price' => '500', 'vat_rate' => '15']],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $invoice = Invoice::query()->sole();
        $this->assertSame('5750.00', (string) $invoice->total);

        $this->actingAs($this->owner)->get($this->url("invoices/{$invoice->id}"))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Invoicing/Invoices/Show')->where('can.issue', true)->has('units', 1));

        $this->actingAs($this->owner)->post($this->url("invoices/{$invoice->id}/issue"), ['unit_id' => EinvoiceUnit::query()->value('id')])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('cleared', $invoice->fresh()->status->value);
        $this->assertSame('INV-2026-00001', $invoice->fresh()->number);

        $this->actingAs($this->owner)->get($this->url("invoices/{$invoice->id}/xml"))->assertOk()->assertHeader('Content-Type', 'application/xml');
        $this->actingAs($this->owner)->get($this->url("invoices/{$invoice->id}/pdf"))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->owner)->get($this->url("invoices/{$invoice->id}/edit"))->assertForbidden(); // no longer a draft
        $this->actingAs($this->owner)->get($this->url('invoices'))->assertInertia(fn (Assert $page) => $page->has('invoices.data', 1)->where('invoices.data.0.status', 'cleared'));
    }

    public function test_units_are_onboarded_with_an_otp_through_the_page(): void
    {
        Http::fake([
            '*/compliance' => Http::response(['requestID' => 1234567890, 'binarySecurityToken' => base64_encode('cert'), 'secret' => 'shh', 'dispositionMessage' => 'ISSUED'], 200),
            '*/production/csids' => Http::response(['requestID' => 1234567891, 'binarySecurityToken' => base64_encode('prod-cert'), 'secret' => 'prod-shh'], 200),
        ]);

        $this->actingAs($this->owner)->post($this->url('units'), ['company_id' => $this->company->id, 'name' => 'POS-1', 'environment' => 'sandbox'])->assertSessionHasNoErrors()->assertRedirect();
        $unit = EinvoiceUnit::query()->sole();
        $this->assertSame('new', $unit->status->value);
        $this->assertStringContainsString('BEGIN CERTIFICATE REQUEST', $unit->csr);
        $this->assertStringStartsWith('1-Baydaq|2-pos-1|3-', $unit->serial);

        $this->actingAs($this->owner)->post($this->url("units/{$unit->id}/compliance"), ['otp' => '123456'])->assertSessionHasNoErrors();
        $this->assertSame('compliance', $unit->fresh()->status->value);
        $this->assertSame('1234567890', $unit->fresh()->compliance_request_id);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/compliance') && $request->hasHeader('OTP', '123456'));

        $this->actingAs($this->owner)->post($this->url("units/{$unit->id}/production"))->assertSessionHasNoErrors();
        $this->assertSame('production', $unit->fresh()->status->value);
        $this->assertSame(base64_encode('prod-cert'), $unit->fresh()->credentials()['certificate']);

        $this->actingAs($this->owner)->get($this->url('units'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('units', 1)->where('units.0.status', 'production'));
    }

    public function test_a_company_without_a_vat_number_cannot_onboard_and_a_viewer_cannot_issue(): void
    {
        Company::query()->whereKey($this->company->id)->update(['vat_number' => null]);
        $this->actingAs($this->owner)->post($this->url('units'), ['company_id' => $this->company->id, 'name' => 'POS-1', 'environment' => 'sandbox'])->assertSessionHasErrors('company_id');

        $viewer = $this->memberWith([InvoicingFeature::Invoices->key('view')], ScopeLevel::Company, $this->company);
        $this->actingAs($viewer)->get($this->url('invoices', 'c-'.$this->company->slug))->assertOk();
        $this->actingAs($viewer)->get($this->url('invoices/create', 'c-'.$this->company->slug))->assertForbidden();
        $this->actingAs($viewer)->get($this->url('units', 'c-'.$this->company->slug))->assertForbidden();
    }

    public function test_an_issued_invoice_is_sent_as_a_whatsapp_payment_link_and_marked_paid(): void
    {
        $this->fakeZatcaAccepting();
        $this->readyUnit($this->company->id, $this->client->id);
        $customer = (new Customer(['name_ar' => 'عميل', 'name_en' => 'Buyer', 'phone' => '0551234567']))->forceFill(['client_id' => $this->client->id]);
        $customer->save();
        $invoice = app(SaveInvoice::class)->handle(new Invoice, $this->company->id, ['type' => 'simplified', 'kind' => 'invoice', 'customer_id' => $customer->id, 'issue_date' => '2026-11-01', 'payment_means' => '10'], [['description' => 'Service', 'quantity' => 1, 'unit_price' => '100']]);

        $this->actingAs($this->owner)->post($this->url("invoices/{$invoice->id}/payment-link"))->assertSessionHasErrors('invoice'); // still a draft

        $this->actingAs($this->owner)->post($this->url("invoices/{$invoice->id}/issue"), ['unit_id' => EinvoiceUnit::query()->value('id')])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post($this->url("invoices/{$invoice->id}/payment-link"))->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post($this->url("invoices/{$invoice->id}/payment-link"))->assertSessionHasNoErrors(); // reuses the open link

        $this->assertSame(1, Payment::query()->count());
        $message = Message::query()->where('template', 'inv_payment_link')->latest('id')->firstOrFail();
        $this->assertSame('966551234567', $message->to);
        $this->assertStringContainsString(Payment::query()->value('token'), $message->body);

        $this->post('/pay/'.Payment::query()->value('token'));
        $this->assertNotNull($invoice->fresh()->paid_at);
    }

    public function test_the_units_page_shows_where_each_company_stands_in_onboarding(): void
    {
        $other = Company::factory()->for($this->organization)->create(['client_id' => $this->client->id, 'vat_number' => null]);

        $this->actingAs($this->owner)->get($this->url('units'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('readiness', fn ($rows) => collect($rows)->firstWhere('company_id', $this->company->id)['step'] === 2
                && collect($rows)->firstWhere('company_id', $other->id)['step'] === 1
                && in_array('vat_number', collect($rows)->firstWhere('company_id', $other->id)['missing'], true)));

        $this->readyUnit($this->company->id, $this->client->id);

        $this->actingAs($this->owner)->get($this->url('units'))->assertInertia(fn (Assert $page) => $page
            ->where('readiness', fn ($rows) => collect($rows)->firstWhere('company_id', $this->company->id)['step'] === 4));
    }

    public function test_group_totals_leave_intercompany_sales_out_and_the_list_filters_them(): void
    {
        $other = Company::factory()->for($this->organization)->create(['client_id' => $this->client->id]);
        $line = [['description' => 'Service', 'quantity' => 1, 'unit_price' => '100.00']];
        $external = app(SaveInvoice::class)->handle(new Invoice, $this->company->id, ['type' => 'simplified', 'issue_date' => '2026-11-01', 'payment_means' => '10'], $line);
        $internal = app(SaveInvoice::class)->handle(new Invoice, $this->company->id, ['type' => 'simplified', 'issue_date' => '2026-11-01', 'payment_means' => '42'], $line);
        $external->forceFill(['status' => 'reported'])->save();
        $internal->forceFill(['status' => 'reported', 'intercompany' => true])->save();
        app(SaveInvoice::class)->handle(new Invoice, $other->id, ['type' => 'simplified', 'issue_date' => '2026-11-01', 'payment_means' => '10'], $line)->forceFill(['status' => 'reported'])->save();

        $this->actingAs($this->owner)->get($this->url('invoices'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('group.group_external', '230.00')
            ->where('group.intercompany', '115.00')
            ->has('group.companies', 2));

        $this->actingAs($this->owner)->get($this->url('invoices').'?intercompany=only')->assertInertia(fn (Assert $page) => $page
            ->has('invoices.data', 1)->where('invoices.data.0.id', $internal->id)->where('invoices.data.0.intercompany', true));
        $this->actingAs($this->owner)->get($this->url('invoices').'?intercompany=exclude')->assertInertia(fn (Assert $page) => $page->has('invoices.data', 2));
    }

    public function test_a_baydaq_invoice_sent_from_the_admin_is_issued_as_an_einvoice_from_the_chosen_company(): void
    {
        $this->readyUnit($this->company->id, $this->client->id);
        $this->fakeZatcaAccepting();
        $admin = User::factory()->admin(Permission::InvoicesManage, Permission::SettingsManage)->create();
        $invoice = CoreInvoice::factory()->create();
        $invoice->items()->make(['description' => 'Website build', 'quantity' => 1, 'unit_price' => '1000.00'])->forceFill(['total' => '1000.00'])->save();

        $this->actingAs($admin)->put('/admin/settings', ['einvoicing_company_id' => $this->company->id])->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($admin)->post("/admin/invoices/{$invoice->id}/send")->assertRedirect();

        $invoice->refresh();
        $this->assertSame('sent', $invoice->status->value);
        $this->assertNotNull($invoice->einvoice_number);
        $this->assertSame('reported', $invoice->einvoice_status);
        $einvoice = Invoice::query()->findOrFail($invoice->einvoice_id);
        $this->assertSame('baydaq.invoice', $einvoice->source);
        $this->assertSame('1150.00', (string) $einvoice->total);
        $this->assertSame($invoice->client->name_ar, $einvoice->customer->name_ar);
    }

    public function test_sending_still_works_when_the_chosen_company_has_no_ready_unit(): void
    {
        $admin = User::factory()->admin(Permission::InvoicesManage)->create();
        $invoice = CoreInvoice::factory()->create();
        Setting::store(['einvoicing_company_id' => (string) $this->company->id]);

        $this->actingAs($admin)->post("/admin/invoices/{$invoice->id}/send")->assertRedirect();

        $this->assertSame('sent', $invoice->refresh()->status->value);
        $this->assertNull($invoice->einvoice_id);
    }
}
