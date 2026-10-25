<?php

namespace App\Modules\Subscriptions;

use App\Modules\Hr\Events\EmployeeHired;
use App\Modules\Hr\Events\EmployeeTerminated;
use App\Modules\Platform\Access\FeatureRegistry;
use App\Modules\Platform\Ports\CustomerActivity;
use App\Modules\Platform\Ports\SuiteSearch;
use App\Modules\Subscriptions\Adapters\MembershipCustomerActivity;
use App\Modules\Subscriptions\Adapters\MemberSuiteSearch;
use App\Modules\Subscriptions\Console\ExpireMemberships;
use App\Modules\Subscriptions\Enums\SubscriptionsFeature;
use App\Modules\Subscriptions\Listeners\SyncCorporateMembership;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Club memberships: plans, members, freezes and the door. Invoices renewals through Invoicing's
 * InvoiceIssuer; never its tables.
 */
class SubscriptionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([MemberSuiteSearch::class], SuiteSearch::TAG);
        $this->app->tag([MembershipCustomerActivity::class], CustomerActivity::TAG);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->app->make(FeatureRegistry::class)->register(SubscriptionsFeature::class);
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        Event::listen([EmployeeHired::class, EmployeeTerminated::class], SyncCorporateMembership::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireMemberships::class]);
        }
    }
}
