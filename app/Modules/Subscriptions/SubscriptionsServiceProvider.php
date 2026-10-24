<?php

namespace App\Modules\Subscriptions;

use App\Modules\Platform\Access\FeatureRegistry;
use App\Modules\Subscriptions\Console\ExpireMemberships;
use App\Modules\Subscriptions\Enums\SubscriptionsFeature;
use Illuminate\Support\ServiceProvider;

/**
 * Club memberships: plans, members, freezes and the door. Invoices renewals through Invoicing's
 * InvoiceIssuer; never its tables.
 */
class SubscriptionsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->app->make(FeatureRegistry::class)->register(SubscriptionsFeature::class);
        $this->loadRoutesFrom(__DIR__.'/routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireMemberships::class]);
        }
    }
}
