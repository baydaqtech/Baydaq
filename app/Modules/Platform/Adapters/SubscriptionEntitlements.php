<?php

namespace App\Modules\Platform\Adapters;

use App\Modules\Platform\Access\EffectivePlan;
use App\Modules\Platform\Access\FeatureCatalog;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Models\ClientPlan;
use App\Modules\Platform\Models\Package;
use App\Modules\Platform\Models\Subscription;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Tenancy\CurrentTenant;

/**
 * Modules come from the per-module subscriptions (which applying a plan writes); limits and
 * feature flags come from the client's plan, or the default package when it has none.
 */
class SubscriptionEntitlements implements Entitlements
{
    /** @var array<int, EffectivePlan|false> worked-out plan per client, for this request */
    private array $plans = [];

    public function __construct(private CurrentTenant $tenant) {}

    public function isActive(Module $module): bool
    {
        return $this->current($module)?->isActive() ?? false;
    }

    public function quantity(Module $module): ?int
    {
        return $this->current($module)?->quantity;
    }

    public function lockQuantity(Module $module): ?int
    {
        return Subscription::query()->forModule($module)->latest('starts_on')->lockForUpdate()->first()?->quantity;
    }

    public function limit(string $key): ?int
    {
        return $this->plan()?->limits[$key] ?? null;
    }

    public function hasFeature(string $key): bool
    {
        $plan = $this->plan();

        return $plan === null || in_array($key, $plan->features, true);
    }

    public function features(): array
    {
        return $this->plan()?->features ?? FeatureCatalog::features();
    }

    public function mobileFeatures(): array
    {
        return $this->plan()?->mobile ?? FeatureCatalog::MOBILE;
    }

    private function plan(): ?EffectivePlan
    {
        $clientId = $this->tenant->id();

        if ($clientId === null) {
            return null;
        }

        if (! array_key_exists($clientId, $this->plans)) {
            $this->plans[$clientId] = $this->resolve();
        }

        return $this->plans[$clientId] ?: null;
    }

    /**
     * The client's own plan while it is usable, nothing once it is suspended or ended, and the
     * default package for an account without a plan. With no default package, production gives
     * such an account nothing; elsewhere it gets everything (demos and tests).
     */
    private function resolve(): EffectivePlan|false
    {
        $plan = ClientPlan::query()->with('package')->first();

        if ($plan !== null) {
            return $plan->isUsable() ? EffectivePlan::forPlan($plan) : EffectivePlan::none();
        }

        $default = Package::query()->where('is_default', true)->where('is_active', true)->first();

        if ($default !== null) {
            return EffectivePlan::from($default, [], []);
        }

        return app()->isProduction() ? EffectivePlan::none() : false;
    }

    private function current(Module $module): ?Subscription
    {
        return Subscription::query()->forModule($module)->latest('starts_on')->first();
    }
}
