<?php

namespace App\Models\Concerns;

/**
 * For models that store a field twice, as {field}_ar and {field}_en.
 */
trait HasBilingualFields
{
    /**
     * The field in the current language, falling back to the other one when it is empty.
     */
    public function localized(string $field): ?string
    {
        $current = app()->getLocale() === 'en' ? 'en' : 'ar';
        $other = $current === 'en' ? 'ar' : 'en';

        return $this->getAttribute("{$field}_{$current}") ?: $this->getAttribute("{$field}_{$other}");
    }
}
