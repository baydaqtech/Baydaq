<?php

namespace App\Modules\Hr\Support;

/**
 * Folds a name into a comparison key so "HR", " hr " and "Hr" are one job, and so are the
 * Arabic spellings that differ only in hamza, taa marbuta, alef maqsura, diacritics or tatweel.
 */
final class ArabicText
{
    public static function key(string $value): string
    {
        $value = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
        $value = preg_replace('/[\x{064B}-\x{0652}\x{0670}\x{0640}]/u', '', $value) ?? $value; // diacritics, tatweel
        $value = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $value);
        $value = str_replace('ة', 'ه', $value);
        $value = str_replace('ى', 'ي', $value);
        $value = str_replace('ؤ', 'و', $value);
        $value = str_replace('ئ', 'ي', $value);

        return $value;
    }
}
