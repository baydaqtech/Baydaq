<?php

namespace App\Modules\Platform\Actions\Plans;

use App\Models\Client;
use App\Modules\Platform\Access\EffectivePlan;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Enums\SubscriptionStatus;
use App\Modules\Platform\Models\ClientPlan;
use App\Modules\Platform\Models\Package;
use App\Modules\Platform\Models\Subscription;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Put a client on a package (with add-ons and custom overrides). The per-module subscriptions the
 * suite already checks are written to match: modules in the plan become active with their cap
 * taken from the plan's limits; modules no longer in the plan are cancelled.
 */
class ApplyPlan
{
    /** A module's subscription quantity comes from this plan limit. */
    private const QUANTITY_FROM = [
        'hr' => 'employees', 'pos' => 'registers', 'invoicing' => 'companies', 'assets' => 'companies',
        'reservations' => 'branches', 'subscriptions' => 'branches', 'menus' => 'branches',
    ];

    /** Stored as the quantity when the plan limit is unlimited. */
    public const UNLIMITED = 100000;

    public function __construct(private CurrentTenant $tenant) {}

    /**
     * @param  list<array{addon_id: int, quantity?: int}>  $addons
     * @param  array{limits?: array<string, int|null>, features?: array<string, bool>, mobile?: array<string, bool>}  $overrides
     */
    public function handle(Client $client, Package $package, array $addons, array $overrides, string $status, string $startsOn, ?string $endsOn, string $billingCycle = 'monthly'): ClientPlan
    {
        return $this->tenant->run($client, fn (): ClientPlan => DB::transaction(function () use ($client, $package, $addons, $overrides, $status, $startsOn, $endsOn, $billingCycle): ClientPlan {
            $plan = ClientPlan::query()->first() ?? new ClientPlan;
            $plan->forceFill([
                'client_id' => $client->id, 'package_id' => $package->id, 'addons' => array_values($addons), 'overrides' => $overrides,
                'status' => $status, 'billing_cycle' => $billingCycle, 'starts_on' => $startsOn, 'ends_on' => $endsOn,
            ])->save();

            $effective = EffectivePlan::from($package, $addons, $overrides);
            $usable = $status !== 'suspended';

            foreach (Module::cases() as $module) {
                $subscription = Subscription::query()->forModule($module)->latest('starts_on')->first();
                $included = in_array($module->value, $effective->modules, true);

                if (! $included) {
                    $subscription?->forceFill(['status' => SubscriptionStatus::Cancelled])->save();

                    continue;
                }

                $limit = $effective->limits[self::QUANTITY_FROM[$module->value] ?? ''] ?? null;

                ($subscription ?? (new Subscription)->forceFill(['client_id' => $client->id, 'module' => $module, 'billing_unit' => $module->billingUnit(), 'unit_price' => 0]))
                    ->forceFill([
                        'status' => $usable ? SubscriptionStatus::Active : SubscriptionStatus::Lapsed,
                        'quantity' => $limit ?? self::UNLIMITED,
                        'starts_on' => $subscription?->starts_on?->toDateString() ?? $startsOn,
                        'ends_on' => $endsOn,
                        'notes' => __('suite.plans.from_package', ['package' => $package->localized('name')]),
                    ])->save();
            }

            return $plan->load('package');
        }));
    }

    /** Trial end from the package's trial days. */
    public static function trialEnd(Package $package, ?Carbon $from = null): ?string
    {
        return $package->trial_days > 0 ? ($from ?? today())->copy()->addDays($package->trial_days)->toDateString() : null;
    }
}
