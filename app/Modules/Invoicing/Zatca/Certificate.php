<?php

namespace App\Modules\Invoicing\Zatca;

use RuntimeException;

/**
 * Reads what the signature needs from a ZATCA certificate (base64 DER as ZATCA returns it):
 * the digest ZATCA expects in SignedProperties, the issuer, the serial, and the certificate's
 * own signature bytes (QR tag 9 on simplified invoices).
 */
final class Certificate
{
    private string $der;

    /** @var array<string, mixed> */
    private array $parsed;

    public function __construct(public readonly string $base64)
    {
        $this->der = base64_decode($base64, true) ?: throw new RuntimeException('Certificate is not valid base64.');
        $this->parsed = openssl_x509_parse($this->pem()) ?: throw new RuntimeException('Certificate could not be parsed.');
    }

    public function pem(): string
    {
        return "-----BEGIN CERTIFICATE-----\n".chunk_split($this->base64, 64, "\n").'-----END CERTIFICATE-----';
    }

    /** ZATCA: base64( sha256-hex( certificate base64 ) ). */
    public function digest(): string
    {
        return base64_encode(hash('sha256', $this->base64));
    }

    public function issuerName(): string
    {
        $parts = [];

        foreach (array_reverse($this->parsed['issuer'], true) as $key => $value) {
            foreach ((array) $value as $item) {
                $parts[] = "{$key}={$item}";
            }
        }

        return implode(', ', $parts);
    }

    public function serialNumber(): string
    {
        return $this->parsed['serialNumber'];
    }

    /** The CA's signature over the certificate: the last BIT STRING of the outer SEQUENCE. */
    public function signature(): string
    {
        [$offset, $length] = $this->tlv($this->der, 0);          // outer SEQUENCE
        $cursor = $offset;
        $end = $offset + $length;
        $last = null;

        while ($cursor < $end) {
            [$contentOffset, $contentLength] = $this->tlv($this->der, $cursor);
            $last = [$contentOffset, $contentLength];
            $cursor = $contentOffset + $contentLength;
        }

        if ($last === null) {
            throw new RuntimeException('Certificate has no signature.');
        }

        // BIT STRING content starts with the unused-bits byte.
        return substr($this->der, $last[0] + 1, $last[1] - 1);
    }

    /**
     * Parse one DER TLV at $pos: returns [content offset, content length].
     *
     * @return array{0: int, 1: int}
     */
    private function tlv(string $der, int $pos): array
    {
        $length = ord($der[$pos + 1]);
        $cursor = $pos + 2;

        if ($length & 0x80) {
            $bytes = $length & 0x7F;
            $length = 0;

            for ($i = 0; $i < $bytes; $i++) {
                $length = ($length << 8) | ord($der[$cursor++]);
            }
        }

        return [$cursor, $length];
    }
}
