<?php

namespace App\Modules\Platform;

use App\Modules\Platform\Access\FeatureRegistry;
use App\Modules\Platform\Adapters\AuditTrail;
use App\Modules\Platform\Adapters\ResolvedAccess;
use App\Modules\Platform\Adapters\SubscriptionEntitlements;
use App\Modules\Platform\Adapters\TenantAccounts;
use App\Modules\Platform\Adapters\TenantOrgDirectory;
use App\Modules\Platform\Enums\SuiteFeature;
use App\Modules\Platform\Ports\Access;
use App\Modules\Platform\Ports\Accounts;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Support\ServiceProvider;

/**
 * The suite kernel: tenant tree, access, subscriptions and audit. Modules plug their
 * features into the registry from their own providers.
 */
class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FeatureRegistry::class);
        $this->app->bind(Entitlements::class, SubscriptionEntitlements::class);
        $this->app->bind(Access::class, ResolvedAccess::class);
        $this->app->bind(OrgDirectory::class, TenantOrgDirectory::class);
        $this->app->bind(Accounts::class, TenantAccounts::class);
        $this->app->bind(Audit::class, AuditTrail::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        $this->app->make(FeatureRegistry::class)->register(SuiteFeature::class);

        if (file_exists(__DIR__.'/routes.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes.php');
        }
    }
}
