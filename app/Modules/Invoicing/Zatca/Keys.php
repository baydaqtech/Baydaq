<?php

namespace App\Modules\Invoicing\Zatca;

use App\Modules\Invoicing\Enums\ZatcaEnvironment;
use RuntimeException;

/**
 * Key pair and certificate signing request for an e-invoicing unit, exactly as ZATCA's onboarding
 * expects: an EC secp256k1 key, a subject with the company's names, and the ZATCA extension that
 * carries the unit serial, the VAT number, the invoice types, the address and the business category.
 */
class Keys
{
    public function generatePrivateKey(): string
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp256k1']) ?: throw new RuntimeException('Could not create the EC key.');
        openssl_pkey_export($key, $pem);

        return $pem;
    }

    /**
     * @param  array{common_name: string, organization: string, organization_unit: string, serial: string, vat_number: string, invoice_types: string, address: string, business_category: string}  $subject
     */
    public function csr(string $privateKeyPem, ZatcaEnvironment $environment, array $subject): string
    {
        $template = match ($environment) {
            ZatcaEnvironment::Sandbox => 'TSTZATCA-Code-Signing',
            ZatcaEnvironment::Simulation => 'PREZATCA-Code-Signing',
            ZatcaEnvironment::Production => 'ZATCA-Code-Signing',
        };

        $config = tempnam(sys_get_temp_dir(), 'zatca');
        file_put_contents($config, <<<CNF
        oid_section = OIDs
        [ OIDs ]
        certificateTemplateName = 1.3.6.1.4.1.311.20.2
        [ req ]
        default_bits = 2048
        prompt = no
        default_md = sha256
        req_extensions = req_ext
        distinguished_name = dn
        [ dn ]
        [ req_ext ]
        certificateTemplateName = ASN1:PRINTABLESTRING:{$template}
        subjectAltName = dirName:alt_names
        [ alt_names ]
        SN = {$this->clean($subject['serial'])}
        UID = {$this->clean($subject['vat_number'])}
        title = {$this->clean($subject['invoice_types'])}
        registeredAddress = {$this->clean($subject['address'])}
        businessCategory = {$this->clean($subject['business_category'])}
        CNF);

        try {
            $dn = ['countryName' => 'SA', 'organizationalUnitName' => $this->clean($subject['organization_unit']), 'organizationName' => $this->clean($subject['organization']), 'commonName' => $this->clean($subject['common_name'])];
            $csr = openssl_csr_new($dn, $privateKeyPem, ['config' => $config, 'digest_alg' => 'sha256', 'req_extensions' => 'req_ext', 'curve_name' => 'secp256k1'])
                ?: throw new RuntimeException('Could not create the CSR: '.openssl_error_string());
            openssl_csr_export($csr, $pem);
        } finally {
            @unlink($config);
        }

        return $pem;
    }

    /** DER bytes of the public key, as the QR (tag 8) carries them. */
    public function publicKeyDer(string $privateKeyPem): string
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_private($privateKeyPem) ?: throw new RuntimeException('Bad private key.'));

        return base64_decode(preg_replace('/-----.*?-----|\s/', '', $details['key']) ?? '', true) ?: '';
    }

    private function clean(string $value): string
    {
        return str_replace(["\n", "\r", '"'], ' ', $value);
    }
}
