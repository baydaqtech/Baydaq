<?php

namespace App\Modules\Invoicing\Zatca;

use RuntimeException;

/**
 * Stamps an invoice: ECDSA-SHA256 signature over the invoice hash, the XAdES signed properties
 * with the certificate digest, and the UBLExtensions block that carries them all. Produces the
 * QR payload too, since it needs the same pieces.
 */
class Signer
{
    public function __construct(private Keys $keys) {}

    /**
     * @return array{xml: string, hash: string, signature: string, qr: string}
     */
    public function sign(string $unsignedXml, string $privateKeyPem, Certificate $certificate, string $sellerName, string $vatNumber, string $issuedAt, string $total, string $vat, bool $simplified): array
    {
        $hash = InvoiceHash::of($unsignedXml);
        $hashBytes = base64_decode($hash, true);

        $key = openssl_pkey_get_private($privateKeyPem) ?: throw new RuntimeException('Bad private key.');
        openssl_sign($hashBytes, $signature, $key, OPENSSL_ALGO_SHA256) ?: throw new RuntimeException('Signing failed.');
        $signatureBase64 = base64_encode($signature);

        $signingTime = gmdate('Y-m-d\TH:i:s\Z');
        $signedProperties = $this->signedProperties($signingTime, $certificate);
        $signedPropertiesDigest = base64_encode(hash('sha256', $signedProperties));

        $qrTags = [1 => $sellerName, 2 => $vatNumber, 3 => $issuedAt, 4 => $total, 5 => $vat, 6 => $hash, 7 => $signature, 8 => $this->keys->publicKeyDer($privateKeyPem)];

        if ($simplified) {
            $qrTags[9] = $certificate->signature();
        }

        $qr = QrCode::encode($qrTags);
        $xml = $this->embed($unsignedXml, $hash, $signatureBase64, $signedProperties, $signedPropertiesDigest, $certificate, $qr);

        return ['xml' => $xml, 'hash' => $hash, 'signature' => $signatureBase64, 'qr' => $qr];
    }

    private function signedProperties(string $signingTime, Certificate $certificate): string
    {
        $digest = $certificate->digest();
        $issuer = htmlspecialchars($certificate->issuerName(), ENT_XML1);
        $serial = $certificate->serialNumber();

        return <<<XML
        <xades:SignedProperties xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" Id="xadesSignedProperties">
                                            <xades:SignedSignatureProperties>
                                                <xades:SigningTime>{$signingTime}</xades:SigningTime>
                                                <xades:SigningCertificate>
                                                    <xades:Cert>
                                                        <xades:CertDigest>
                                                            <ds:DigestMethod xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
                                                            <ds:DigestValue xmlns:ds="http://www.w3.org/2000/09/xmldsig#">{$digest}</ds:DigestValue>
                                                        </xades:CertDigest>
                                                        <xades:IssuerSerial>
                                                            <ds:X509IssuerName xmlns:ds="http://www.w3.org/2000/09/xmldsig#">{$issuer}</ds:X509IssuerName>
                                                            <ds:X509SerialNumber xmlns:ds="http://www.w3.org/2000/09/xmldsig#">{$serial}</ds:X509SerialNumber>
                                                        </xades:IssuerSerial>
                                                    </xades:Cert>
                                                </xades:SigningCertificate>
                                            </xades:SignedSignatureProperties>
                                        </xades:SignedProperties>
        XML;
    }

    private function embed(string $xml, string $hash, string $signature, string $signedProperties, string $signedPropertiesDigest, Certificate $certificate, string $qr): string
    {
        // The SignedProperties inside the document carry no xmlns:xades (it is declared on QualifyingProperties).
        $propertiesInDocument = str_replace(' xmlns:xades="http://uri.etsi.org/01903/v1.3.2#"', '', $signedProperties);

        $extensions = <<<XML
        <ext:UBLExtensions>
                <ext:UBLExtension>
                    <ext:ExtensionURI>urn:oasis:names:specification:ubl:dsig:enveloped:xades</ext:ExtensionURI>
                    <ext:ExtensionContent>
                        <sig:UBLDocumentSignatures xmlns:sig="urn:oasis:names:specification:ubl:schema:xsd:CommonSignatureComponents-2" xmlns:sac="urn:oasis:names:specification:ubl:schema:xsd:SignatureAggregateComponents-2" xmlns:sbc="urn:oasis:names:specification:ubl:schema:xsd:SignatureBasicComponents-2">
                            <sac:SignatureInformation>
                                <cbc:ID>urn:oasis:names:specification:ubl:signature:1</cbc:ID>
                                <sbc:ReferencedSignatureID>urn:oasis:names:specification:ubl:signature:Invoice</sbc:ReferencedSignatureID>
                                <ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Id="signature">
                                    <ds:SignedInfo>
                                        <ds:CanonicalizationMethod Algorithm="http://www.w3.org/2006/12/xml-c14n11"/>
                                        <ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256"/>
                                        <ds:Reference Id="invoiceSignedData" URI="">
                                            <ds:Transforms>
                                                <ds:Transform Algorithm="http://www.w3.org/TR/1999/REC-xpath-19991116">
                                                    <ds:XPath>not(//ancestor-or-self::ext:UBLExtensions)</ds:XPath>
                                                </ds:Transform>
                                                <ds:Transform Algorithm="http://www.w3.org/TR/1999/REC-xpath-19991116">
                                                    <ds:XPath>not(//ancestor-or-self::cac:Signature)</ds:XPath>
                                                </ds:Transform>
                                                <ds:Transform Algorithm="http://www.w3.org/TR/1999/REC-xpath-19991116">
                                                    <ds:XPath>not(//ancestor-or-self::cac:AdditionalDocumentReference[cbc:ID='QR'])</ds:XPath>
                                                </ds:Transform>
                                                <ds:Transform Algorithm="http://www.w3.org/2006/12/xml-c14n11"/>
                                            </ds:Transforms>
                                            <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
                                            <ds:DigestValue>{$hash}</ds:DigestValue>
                                        </ds:Reference>
                                        <ds:Reference Type="http://www.w3.org/2000/09/xmldsig#SignatureProperties" URI="#xadesSignedProperties">
                                            <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
                                            <ds:DigestValue>{$signedPropertiesDigest}</ds:DigestValue>
                                        </ds:Reference>
                                    </ds:SignedInfo>
                                    <ds:SignatureValue>{$signature}</ds:SignatureValue>
                                    <ds:KeyInfo>
                                        <ds:X509Data>
                                            <ds:X509Certificate>{$certificate->base64}</ds:X509Certificate>
                                        </ds:X509Data>
                                    </ds:KeyInfo>
                                    <ds:Object>
                                        <xades:QualifyingProperties xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" Target="signature">
                                            {$propertiesInDocument}
                                        </xades:QualifyingProperties>
                                    </ds:Object>
                                </ds:Signature>
                            </sac:SignatureInformation>
                        </sig:UBLDocumentSignatures>
                    </ext:ExtensionContent>
                </ext:UBLExtension>
            </ext:UBLExtensions>
        XML;

        $qrReference = <<<XML
        <cac:AdditionalDocumentReference>
                <cbc:ID>QR</cbc:ID>
                <cac:Attachment>
                    <cbc:EmbeddedDocumentBinaryObject mimeCode="text/plain">{$qr}</cbc:EmbeddedDocumentBinaryObject>
                </cac:Attachment>
            </cac:AdditionalDocumentReference>
            <cac:Signature>
                <cbc:ID>urn:oasis:names:specification:ubl:signature:Invoice</cbc:ID>
                <cbc:SignatureMethod>urn:oasis:names:specification:ubl:dsig:enveloped:xades</cbc:SignatureMethod>
            </cac:Signature>
        XML;

        $xml = preg_replace('/(<Invoice[^>]*>)\s*/', "$1\n    ".$extensions."\n    ", $xml, 1);

        return preg_replace('/(\s*)(<cac:AccountingSupplierParty>)/', '$1'.str_replace(['\\', '$'], ['\\\\', '\\$'], $qrReference).'$1$2', $xml, 1);
    }
}
