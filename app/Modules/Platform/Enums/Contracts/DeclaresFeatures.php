<?php

namespace App\Modules\Platform\Enums\Contracts;

use App\Modules\Platform\Enums\FeatureAction;

/**
 * Shared implementation of Feature::key() for string-backed feature enums.
 */
trait DeclaresFeatures
{
    public function key(FeatureAction|string $action): string
    {
        $name = $action instanceof FeatureAction ? $action->value : $action;

        return static::group().'.'.$this->value.'.'.$name;
    }

    /**
     * Every permission key this feature offers.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return [
            ...array_map(fn (FeatureAction $action): string => $this->key($action), $this->actions()),
            ...array_map(fn (string $ability): string => $this->key($ability), $this->abilities()),
        ];
    }
}
