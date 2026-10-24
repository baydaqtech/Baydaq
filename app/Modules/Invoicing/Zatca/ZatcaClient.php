<?php

namespace App\Modules\Invoicing\Zatca;

use App\Modules\Invoicing\Enums\ZatcaEnvironment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The Fatoora API: onboarding (compliance CSID with an OTP, compliance checks, production CSID)
 * and the two ways an invoice reaches ZATCA (clearance for standard, reporting for simplified).
 * Every call returns the decoded JSON plus the HTTP status so the caller can store it verbatim.
 */
class ZatcaClient
{
    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function complianceCsid(ZatcaEnvironment $environment, string $csrPem, string $otp): array
    {
        return $this->result($this->http($environment)->withHeaders(['OTP' => $otp])->post('/compliance', ['csr' => base64_encode($csrPem)]));
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function complianceCheck(ZatcaEnvironment $environment, string $certificate, string $secret, string $uuid, string $hash, string $signedXml): array
    {
        return $this->result($this->http($environment)->withBasicAuth($certificate, $secret)->post('/compliance/invoices', [
            'invoiceHash' => $hash, 'uuid' => $uuid, 'invoice' => base64_encode($signedXml),
        ]));
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function productionCsid(ZatcaEnvironment $environment, string $certificate, string $secret, string $complianceRequestId): array
    {
        return $this->result($this->http($environment)->withBasicAuth($certificate, $secret)->post('/production/csids', ['compliance_request_id' => $complianceRequestId]));
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function clear(ZatcaEnvironment $environment, string $certificate, string $secret, string $uuid, string $hash, string $signedXml): array
    {
        return $this->result($this->http($environment)->withBasicAuth($certificate, $secret)->withHeaders(['Clearance-Status' => '1'])->post('/invoices/clearance/single', [
            'invoiceHash' => $hash, 'uuid' => $uuid, 'invoice' => base64_encode($signedXml),
        ]));
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function report(ZatcaEnvironment $environment, string $certificate, string $secret, string $uuid, string $hash, string $signedXml): array
    {
        return $this->result($this->http($environment)->withBasicAuth($certificate, $secret)->withHeaders(['Clearance-Status' => '0'])->post('/invoices/reporting/single', [
            'invoiceHash' => $hash, 'uuid' => $uuid, 'invoice' => base64_encode($signedXml),
        ]));
    }

    private function http(ZatcaEnvironment $environment): PendingRequest
    {
        return Http::baseUrl($environment->baseUrl())
            ->acceptJson()
            ->withHeaders(['Accept-Version' => 'V2', 'Accept-Language' => 'en'])
            ->timeout(30);
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function result(Response $response): array
    {
        return ['status' => $response->status(), 'body' => $response->json() ?? ['raw' => $response->body()]];
    }
}
