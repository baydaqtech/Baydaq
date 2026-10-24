<?php

namespace Tests\Feature\Invoicing;

use App\Modules\Invoicing\Actions\IssueInvoice;
use App\Modules\Invoicing\Actions\SaveCustomer;
use App\Modules\Invoicing\Actions\SaveInvoice;
use App\Modules\Invoicing\Enums\InvoiceStatus;
use App\Modules\Invoicing\Events\InvoiceIssued;
use App\Modules\Invoicing\Models\Customer;
use App\Modules\Invoicing\Models\EinvoiceUnit;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Zatca\InvoiceHash;
use App\Modules\Platform\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsSuite;
use Tests\Concerns\FakesZatca;
use Tests\TestCase;

class IssueInvoiceTest extends TestCase
{
    use BuildsSuite, FakesZatca, RefreshDatabase;

    private EinvoiceUnit $unit;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSuite();
        $this->travelTo('2026-11-01 10:00:00');
        Company::query()->whereKey($this->company->id)->update(['vat_number' => '300000000000003', 'cr_number' => '1010101010', 'street' => 'King Fahd Rd', 'building_number' => '1234', 'district' => 'Olaya', 'city' => 'Riyadh', 'postal_code' => '12211']);
        $this->unit = $this->readyUnit($this->company->id, $this->client->id)['unit'];
        $this->customer = app(SaveCustomer::class)->handle((new Customer)->forceFill(['client_id' => $this->client->id]), [
            'name_ar' => 'شركة العميل', 'name_en' => 'Buyer Co', 'vat_number' => '311111111111113', 'street' => 'Tahlia', 'building_number' => '4321', 'district' => 'Sulimaniyah', 'city' => 'Riyadh', 'postal_code' => '12233',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function draft(array $overrides = []): Invoice
    {
        return app(SaveInvoice::class)->handle(new Invoice, $this->company->id, ['type' => 'simplified', 'issue_date' => '2026-11-01', 'payment_means' => '10', ...$overrides], [
            ['description' => 'Coffee', 'quantity' => 2, 'unit_price' => '15.00'],
            ['description' => 'Cake', 'quantity' => 1, 'unit_price' => '20.00', 'discount' => '5.00'],
        ]);
    }

    public function test_a_draft_computes_lines_and_totals_exactly(): void
    {
        $invoice = $this->draft();

        $this->assertSame('50.00', (string) $invoice->subtotal);
        $this->assertSame('5.00', (string) $invoice->discount);
        $this->assertSame('6.75', (string) $invoice->vat);        // 15% of 45
        $this->assertSame('51.75', (string) $invoice->total);
        $this->assertSame('30.00', (string) $invoice->lines[0]->net);
        $this->assertSame('15.00', (string) $invoice->lines[1]->net);
        $this->assertNull($invoice->number);
    }

    public function test_a_standard_invoice_needs_a_vat_registered_customer_with_an_address(): void
    {
        $this->expectException(ValidationException::class);
        $this->draft(['type' => 'standard']);
    }

    public function test_issuing_a_simplified_invoice_reports_it_signs_it_and_advances_the_chain(): void
    {
        Event::fake([InvoiceIssued::class]);
        $this->fakeZatcaAccepting();

        $invoice = app(IssueInvoice::class)->handle($this->draft(), $this->unit, $this->owner);

        $this->assertSame(InvoiceStatus::Reported, $invoice->status);
        $this->assertSame('INV-2026-00001', $invoice->number);
        $this->assertSame(1, $invoice->counter);
        $this->assertSame(1, $this->unit->fresh()->counter);
        $this->assertSame($invoice->hash, $this->unit->fresh()->previous_hash);

        $submission = $invoice->submissions->first();
        $this->assertSame('reporting', $submission->mode);
        $this->assertSame('accepted', $submission->status);
        $this->assertStringContainsString('<ext:UBLExtensions>', $submission->xml);
        $this->assertStringContainsString('<cbc:ID>QR</cbc:ID>', $submission->xml);
        $this->assertStringContainsString('<cbc:UUID>1</cbc:UUID>', $submission->xml); // ICV
        $this->assertStringContainsString(InvoiceHash::genesis(), $submission->xml);    // PIH of a fresh unit

        // The hash in the XML equals the hash of the XML without signature and QR.
        $this->assertSame($invoice->hash, InvoiceHash::of($submission->xml));

        $qr = $this->decodeQr($invoice->qr);
        $this->assertSame('300000000000003', $qr[2]);
        $this->assertSame('51.75', $qr[4]);
        $this->assertSame('6.75', $qr[5]);
        $this->assertSame($invoice->hash, $qr[6]);
        $this->assertArrayHasKey(9, $qr);

        // The signature verifies against the unit's public key.
        $details = openssl_pkey_get_details(openssl_pkey_get_private($this->unit->private_key));
        $this->assertSame(1, openssl_verify(base64_decode($invoice->hash), $qr[7], $details['key'], OPENSSL_ALGO_SHA256));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/invoices/reporting/single') && $request->hasHeader('Clearance-Status', '0'));
        Event::assertDispatched(InvoiceIssued::class);

        // The second invoice chains on the first.
        $second = app(IssueInvoice::class)->handle($this->draft(), $this->unit->fresh(), $this->owner);
        $this->assertSame('INV-2026-00002', $second->number);
        $this->assertSame(2, $second->counter);
        $this->assertStringContainsString($invoice->hash, $second->submissions->first()->xml);
    }

    public function test_a_standard_invoice_is_cleared_and_the_cleared_copy_is_kept(): void
    {
        $this->fakeZatcaAccepting();

        $invoice = app(IssueInvoice::class)->handle($this->draft(['type' => 'standard', 'customer_id' => $this->customer->id]), $this->unit, $this->owner);

        $this->assertSame(InvoiceStatus::Cleared, $invoice->status);
        $this->assertSame('clearance', $invoice->submissions->first()->mode);
        $this->assertStringContainsString('311111111111113', $invoice->submissions->first()->xml);
        $this->assertArrayNotHasKey(9, $this->decodeQr($invoice->qr));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/invoices/clearance/single') && $request->hasHeader('Clearance-Status', '1'));
    }

    public function test_a_rejection_leaves_the_chain_untouched(): void
    {
        Http::fake(['*' => Http::response(['validationResults' => ['status' => 'ERROR', 'errorMessages' => [['message' => 'BR-KSA-F-06']]]], 400)]);

        $invoice = app(IssueInvoice::class)->handle($this->draft(), $this->unit, $this->owner);

        $this->assertSame(InvoiceStatus::Rejected, $invoice->status);
        $this->assertNull($invoice->counter);
        $this->assertSame(0, $this->unit->fresh()->counter);
        $this->assertSame(InvoiceHash::genesis(), $this->unit->fresh()->previous_hash);
        $this->assertSame('rejected', $invoice->submissions->first()->status);
    }

    public function test_a_credit_note_references_its_invoice(): void
    {
        $this->fakeZatcaAccepting();
        $original = app(IssueInvoice::class)->handle($this->draft(), $this->unit, $this->owner);

        $note = app(SaveInvoice::class)->handle(new Invoice, $this->company->id, ['type' => 'simplified', 'kind' => 'credit_note', 'related_invoice_id' => $original->id, 'issue_date' => '2026-11-02', 'reason' => 'Returned'], [
            ['description' => 'Cake', 'quantity' => 1, 'unit_price' => '15.00'],
        ]);
        $note = app(IssueInvoice::class)->handle($note, $this->unit->fresh(), $this->owner);

        $this->assertSame('CN-2026-00001', $note->number);
        $xml = $note->submissions->first()->xml;
        $this->assertStringContainsString('<cbc:InvoiceTypeCode name="0200000">381</cbc:InvoiceTypeCode>', $xml);
        $this->assertStringContainsString("<cbc:ID>{$original->number}</cbc:ID>", $xml);
        $this->assertStringContainsString('<cbc:InstructionNote>Returned</cbc:InstructionNote>', $xml);
    }
}
