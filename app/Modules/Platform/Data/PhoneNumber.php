<?php

namespace App\Modules\Platform\Data;

/**
 * One rule for phone numbers wherever a phone identifies someone (customers, members, staff on
 * WhatsApp): Saudi mobiles in international form, 9665XXXXXXXX, so 05…, 5…, +9665… and 009665…
 * are the same person. Other numbers keep their digits.
 */
final class PhoneNumber
{
    public static function normalize(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return match (true) {
            str_starts_with($digits, '00') => substr($digits, 2),
            str_starts_with($digits, '05') && strlen($digits) === 10 => '966'.substr($digits, 1),
            str_starts_with($digits, '5') && strlen($digits) === 9 => '966'.$digits,
            default => $digits,
        };
    }
}
