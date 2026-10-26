<?php

namespace App\Modules\Platform\Access;

use App\Modules\Platform\Models\Addon;
use App\Modules\Platform\Models\ClientPlan;
use App\Modules\Platform\Models\Package;

/**
 * A plan worked out: package + add-ons (limits added per quantity) + the admin's overrides.
 */
final readonly class EffectivePlan
{
    /**
     * @param  list<string>  $modules
     * @param  list<string>  $features
     * @param  list<string>  $mobile
     * @param  array<string, int|null>  $limits
     */
    public function __construct(public array $modules, public array $features, public array $mobile, public array $limits) {}

    /**
     * @param  list<array{addon_id: int, quantity?: int}>  $addons
     * @param  array{limits?: array<string, int|null>, features?: array<string, bool>, mobile?: array<string, bool>}  $overrides
     */
    public static function from(Package $package, array $addons, array $overrides): self
    {
        $modules = $package->modules ?? [];
        $features = $package->features ?? [];
        $mobile = $package->mobile ?? [];
        $limits = array_merge(array_fill_keys(FeatureCatalog::LIMITS, null), array_intersect_key($package->limits ?? [], array_flip(FeatureCatalog::LIMITS)));
        $bought = Addon::query()->whereIn('id', array_column($addons, 'addon_id'))->get()->keyBy('id');

        foreach ($addons as $line) {
            $addon = $bought[$line['addon_id']] ?? null;

            if ($addon === null) {
                continue;
            }

            $quantity = max(1, (int) ($line['quantity'] ?? 1));
            $modules = [...$modules, ...($addon->modules ?? [])];
            $features = [...$features, ...($addon->features ?? [])];
            $mobile = [...$mobile, ...($addon->mobile ?? [])];

            foreach ($addon->limits ?? [] as $key => $extra) {
                if (array_key_exists($key, $limits) && $limits[$key] !== null) {
                    $limits[$key] += (int) $extra * $quantity;
                }
            }
        }

        foreach ($overrides['limits'] ?? [] as $key => $value) {
            if (array_key_exists($key, $limits)) {
                $limits[$key] = $value === null || $value === '' ? null : (int) $value;
            }
        }

        $toggle = function (array $list, array $switches): array {
            foreach ($switches as $key => $on) {
                $list = $on ? [...$list, $key] : array_values(array_diff($list, [$key]));
            }

            return array_values(array_unique($list));
        };

        return new self(
            array_values(array_unique($modules)),
            $toggle($features, $overrides['features'] ?? []),
            $toggle($mobile, $overrides['mobile'] ?? []),
            $limits,
        );
    }

    public static function forPlan(ClientPlan $plan): self
    {
        return self::from($plan->package, $plan->addons ?? [], $plan->overrides ?? []);
    }
}
