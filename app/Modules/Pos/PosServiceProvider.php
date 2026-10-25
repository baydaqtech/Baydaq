<?php

namespace App\Modules\Pos;

use App\Modules\Platform\Access\FeatureRegistry;
use App\Modules\Platform\Ports\BoardSignals;
use App\Modules\Platform\Ports\CustomerActivity;
use App\Modules\Platform\Ports\CustomerData;
use App\Modules\Pos\Adapters\PosBoardSignals;
use App\Modules\Pos\Adapters\PosCustomerActivity;
use App\Modules\Pos\Adapters\PosCustomerData;
use App\Modules\Pos\Enums\PosFeature;
use Illuminate\Support\ServiceProvider;

/**
 * Point of sale and QR menus. Uses HR's StaffDirectory for cashiers and Invoicing's
 * InvoiceIssuer for receipts; never their tables.
 */
class PosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([PosBoardSignals::class], BoardSignals::TAG);
        $this->app->tag([PosCustomerActivity::class], CustomerActivity::TAG);
        $this->app->tag([PosCustomerData::class], CustomerData::TAG);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->app->make(FeatureRegistry::class)->register(PosFeature::class);

        if (file_exists(__DIR__.'/routes.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes.php');
        }
    }
}
