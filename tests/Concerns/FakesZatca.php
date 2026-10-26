<?php

namespace Tests\Concerns;

use App\Modules\Invoicing\Models\EinvoiceUnit;
use App\Modules\Invoicing\Zatca\Keys;
use App\Modules\Invoicing\Zatca\QrCode;
use Illuminate\Support\Facades\Http;

/**
 * A unit with a self-signed EC certificate standing in for ZATCA's CSID, and fake Fatoora answers.
 */
trait FakesZatca
{
    /** @return array{unit: EinvoiceUnit, certificate: string} */
    protected function readyUnit(int $companyId, int $clientId, string $environment = 'sandbox'): array
    {
        $keys = new Keys;
        $privateKey = $keys->generatePrivateKey();

        $config = tempnam(sys_get_temp_dir(), 'ssl');
        file_put_contents($config, "[req]\ndistinguished_name=dn\nprompt=no\n[dn]\nC=SA\nO=ZATCA Test CA\nCN=TSTZATCA-Code-Signing");
        $csr = openssl_csr_new(['commonName' => 'unit-test', 'organizationName' => 'Example Co', 'countryName' => 'SA'], $privateKey, ['config' => $config, 'digest_alg' => 'sha256']);
        $x509 = openssl_csr_sign($csr, null, $privateKey, 365, ['config' => $config, 'digest_alg' => 'sha256'], 8201);
        openssl_x509_export($x509, $pem);
        @unlink($config);
        $certificate = preg_replace('/-----.*?-----|\s/', '', $pem);

        $unit = (new EinvoiceUnit(['name' => 'Till 1', 'environment' => $environment]))->forceFill([
            'client_id' => $clientId, 'company_id' => $companyId, 'serial' => '1-Baydaq|2-till|3-'.fake()->uuid(),
            'private_key' => $privateKey, 'csr' => 'csr', 'compliance_certificate' => $certificate, 'compliance_secret' => 'secret',
            'compliance_request_id' => '1234567890', 'status' => 'compliance',
        ]);
        $unit->save();

        return ['unit' => $unit, 'certificate' => $certificate];
    }

    protected function fakeZatcaAccepting(): void
    {
        Http::fake([
            '*/invoices/reporting/single' => Http::response(['reportingStatus' => 'REPORTED', 'validationResults' => ['status' => 'PASS']], 200),
            '*/invoices/clearance/single' => fn ($request) => Http::response(['clearanceStatus' => 'CLEARED', 'clearedInvoice' => $request->data()['invoice'], 'validationResults' => ['status' => 'PASS']], 200),
            '*/compliance/invoices' => Http::response(['reportingStatus' => 'REPORTED', 'validationResults' => ['status' => 'PASS']], 200),
        ]);
    }

    /** @return array<int, string> */
    protected function decodeQr(string $qr): array
    {
        return QrCode::decode($qr);
    }
}
