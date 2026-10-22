<?php

namespace App\Enums\Concerns;

/**
 * Translated labels for string-backed enums, read from lang/{locale}/enums.php
 * under the enum's LABELS group.
 */
trait HasLabels
{
    public function label(): string
    {
        return __('enums.'.self::LABELS.'.'.$this->value);
    }

    /**
     * Value/label pairs for selects and filters.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
