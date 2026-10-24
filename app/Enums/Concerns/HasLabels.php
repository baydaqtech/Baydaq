<?php

namespace App\Enums\Concerns;

/**
 * Translated labels for string-backed enums, read from lang/{locale}/{labelFile}.php
 * under the enum's LABELS group. The file is "enums" unless the enum overrides labelFile();
 * module enums keep their labels in the module's own file (e.g. "hr") so the shared
 * enums group stays small.
 */
trait HasLabels
{
    public function label(): string
    {
        return __(static::labelFile().'.'.self::LABELS.'.'.$this->value);
    }

    /**
     * The lang file holding this enum's labels.
     */
    protected static function labelFile(): string
    {
        return 'enums';
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
