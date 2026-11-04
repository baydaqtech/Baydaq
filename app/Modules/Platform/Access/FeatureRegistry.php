<?php

namespace App\Modules\Platform\Access;

use App\Modules\Platform\Enums\Contracts\Feature;
use App\Modules\Platform\Enums\FeatureAction;
use InvalidArgumentException;

/**
 * Every feature enum the installed modules declare, and so every permission key that
 * exists. Modules register their enum from their service provider; nothing is hard-coded here.
 */
class FeatureRegistry
{
    /** @var list<class-string<Feature>> */
    private array $enums = [];

    /** @var list<string>|null */
    private ?array $keys = null;

    /**
     * @param  class-string<Feature>  $enum
     */
    public function register(string $enum): void
    {
        if (! is_subclass_of($enum, Feature::class)) {
            throw new InvalidArgumentException("{$enum} is not a feature enum.");
        }

        if (! in_array($enum, $this->enums, true)) {
            $this->enums[] = $enum;
            $this->keys = null;
        }
    }

    /**
     * @return list<Feature>
     */
    public function features(): array
    {
        return array_merge(...array_map(fn (string $enum): array => $enum::cases(), $this->enums ?: [[]]));
    }

    /**
     * Every permission key, in registration order.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return $this->keys ??= array_values(array_unique(array_merge(
            ...array_map(fn (Feature $feature): array => $feature->keys(), $this->features() ?: [[]]),
        )));
    }

    public function has(string $key): bool
    {
        return in_array($key, $this->keys(), true);
    }

    /**
     * Features grouped for the permission matrix: group → [{feature, label_file, actions, abilities}].
     *
     * @return array<string, list<array{feature: string, label_file: string, actions: list<string>, abilities: list<string>}>>
     */
    public function matrix(): array
    {
        $groups = [];

        foreach ($this->features() as $feature) {
            $groups[$feature::group()][] = [
                'feature' => $feature->value,
                'label_file' => $feature::labelFile(),
                'actions' => array_map(fn (FeatureAction $action): string => $action->value, $feature->actions()),
                'abilities' => $feature->abilities(),
            ];
        }

        return $groups;
    }
}
