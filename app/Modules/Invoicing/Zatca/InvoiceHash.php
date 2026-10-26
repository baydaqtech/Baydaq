<?php

namespace App\Modules\Invoicing\Zatca;

use DOMDocument;
use DOMXPath;

/**
 * The invoice hash ZATCA chains and signs: SHA-256 of the canonicalized invoice without its
 * UBLExtensions, cac:Signature and the QR document reference, base64-encoded.
 */
final class InvoiceHash
{
    public static function of(string $xml): string
    {
        $document = new DOMDocument;
        $document->preserveWhiteSpace = true;
        $document->formatOutput = false;
        $document->loadXML($xml);

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        foreach (['//ext:UBLExtensions', '//cac:Signature', "//cac:AdditionalDocumentReference[cbc:ID='QR']"] as $query) {
            foreach ($xpath->query($query) as $node) {
                // Drop the whitespace text node that follows, so the canonical form matches ZATCA's SDK.
                if ($node->nextSibling !== null && $node->nextSibling->nodeType === XML_TEXT_NODE && trim($node->nextSibling->nodeValue) === '') {
                    $node->parentNode->removeChild($node->nextSibling);
                }
                $node->parentNode->removeChild($node);
            }
        }

        $canonical = $document->documentElement->C14N(false, false);

        return base64_encode(hash('sha256', $canonical, true));
    }

    /** The hash a brand-new unit uses as "previous invoice hash": base64 of the hex SHA-256 of "0". */
    public static function genesis(): string
    {
        return base64_encode(hash('sha256', '0'));
    }
}
