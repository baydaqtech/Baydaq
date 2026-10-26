<?php

namespace Tests\Feature\Invoicing;

use App\Modules\Invoicing\Enums\ZatcaEnvironment;
use App\Modules\Invoicing\Zatca\Certificate;
use App\Modules\Invoicing\Zatca\InvoiceHash;
use App\Modules\Invoicing\Zatca\Keys;
use App\Modules\Invoicing\Zatca\QrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSuite;
use Tests\Concerns\FakesZatca;
use Tests\TestCase;

class ZatcaEngineTest extends TestCase
{
    use BuildsSuite, FakesZatca, RefreshDatabase;

    public function test_qr_tlv_round_trips_binary_values(): void
    {
        $qr = QrCode::encode([1 => 'شركة المثال', 2 => '300000000000003', 3 => '2026-11-01T10:00:00', 4 => '115.00', 5 => '15.00', 6 => base64_encode(random_bytes(32)), 7 => random_bytes(70)]);
        $tags = QrCode::decode($qr);

        $this->assertSame('شركة المثال', $tags[1]);
        $this->assertSame('300000000000003', $tags[2]);
        $this->assertSame(70, strlen($tags[7]));
    }

    public function test_the_genesis_hash_matches_zatca(): void
    {
        $this->assertSame('NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==', InvoiceHash::genesis());
    }

    public function test_the_invoice_hash_ignores_the_signature_block_and_the_qr(): void
    {
        $bare = '<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">
    <cbc:ID>INV-1</cbc:ID>
    <cac:AdditionalDocumentReference>
        <cbc:ID>PIH</cbc:ID>
    </cac:AdditionalDocumentReference>
</Invoice>';
        $decorated = str_replace('<cbc:ID>INV-1</cbc:ID>', '<ext:UBLExtensions><ext:UBLExtension/></ext:UBLExtensions>
    <cbc:ID>INV-1</cbc:ID>
    <cac:AdditionalDocumentReference><cbc:ID>QR</cbc:ID></cac:AdditionalDocumentReference>
    <cac:Signature><cbc:ID>x</cbc:ID></cac:Signature>', $bare);

        $this->assertSame(InvoiceHash::of($bare), InvoiceHash::of($decorated));
        $this->assertSame(44, strlen(InvoiceHash::of($bare)));
    }

    public function test_keys_and_csr_follow_the_zatca_profile(): void
    {
        $keys = new Keys;
        $pem = $keys->generatePrivateKey();
        $csr = $keys->csr($pem, ZatcaEnvironment::Sandbox, [
            'common_name' => 'POS-1', 'organization' => 'Example Co', 'organization_unit' => 'Riyadh', 'serial' => '1-Baydaq|2-pos|3-abc',
            'vat_number' => '300000000000003', 'invoice_types' => '1100', 'address' => 'Riyadh', 'business_category' => 'Services',
        ]);

        $subject = openssl_csr_get_subject($csr);
        $this->assertSame('SA', $subject['C']);
        $this->assertSame('POS-1', $subject['CN']);
        $this->assertSame('Example Co', $subject['O']);
        $this->assertSame(65, strlen($keys->publicKeyDer($pem)) - 23); // 88-byte DER: 23 bytes of header + 65-byte uncompressed point
    }

    public function test_the_certificate_reader_extracts_digest_issuer_serial_and_signature(): void
    {
        $this->buildSuite();
        $this->asTenant();
        ['certificate' => $base64] = $this->readyUnit($this->company->id, $this->client->id);

        $certificate = new Certificate($base64);

        $this->assertSame(base64_encode(hash('sha256', $base64)), $certificate->digest());
        $this->assertStringContainsString('CN=unit-test', $certificate->issuerName()); // self-signed: issuer = subject
        $this->assertSame('8201', $certificate->serialNumber());
        $this->assertGreaterThan(60, strlen($certificate->signature()));
        $this->assertLessThan(80, strlen($certificate->signature()));
    }
}
