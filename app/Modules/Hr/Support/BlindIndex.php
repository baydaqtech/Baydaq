<?php

namespace App\Modules\Hr\Support;

/**
 * A keyed hash of a sensitive identifier (national id / iqama) so it can be searched and kept
 * unique while the value itself is stored encrypted. Uses its own key, separate from APP_KEY.
 */
final class BlindIndex
{
    public static function of(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return hash_hmac('sha256', preg_replace('/\s+/', '', $value) ?? $value, (string) config('baydaq.hr.blind_index_key'));
    }
}
