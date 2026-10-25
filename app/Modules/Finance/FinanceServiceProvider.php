<?php

namespace App\Modules\Finance;

use App\Modules\Finance\Adapters\GeneralLedger;
use App\Modules\Finance\Enums\FinanceFeature;
use App\Modules\Platform\Access\FeatureRegistry;
use App\Modules\Platform\Ports\Ledger;
use Illuminate\Support\ServiceProvider;

/**
 * Accounting and purchasing: the general ledger every module posts to through the Ledger port,
 * suppliers, purchase orders and bills, and the reports (trial balance, profit and loss, VAT).
 * Switched on by the package features finance.accounting and finance.purchasing.
 */
class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Ledger::class, GeneralLedger::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->app->make(FeatureRegistry::class)->register(FinanceFeature::class);
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}
