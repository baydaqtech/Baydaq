<?php

namespace App\Modules\Invoicing\Zatca;

/**
 * The ZATCA QR payload: TLV-encoded tags, base64. Tags 1–5 are the Phase 1 fields; 6–9 are
 * added for Phase 2 (hash, signature, public key, and the certificate's signature on simplified invoices).
 */
final class QrCode
{
    /**
     * @param  array<int, string>  $tags  tag number → raw value (binary allowed)
     */
    public static function encode(array $tags): string
    {
        $tlv = '';

        foreach ($tags as $tag => $value) {
            $tlv .= chr($tag).chr(strlen($value)).$value;
        }

        return base64_encode($tlv);
    }

    /**
     * @return array<int, string>
     */
    public static function decode(string $base64): array
    {
        $tlv = base64_decode($base64, true) ?: '';
        $tags = [];
        $cursor = 0;

        while ($cursor < strlen($tlv)) {
            $tag = ord($tlv[$cursor]);
            $length = ord($tlv[$cursor + 1]);
            $tags[$tag] = substr($tlv, $cursor + 2, $length);
            $cursor += 2 + $length;
        }

        return $tags;
    }
}
