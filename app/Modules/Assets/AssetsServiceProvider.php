<?php

namespace App\Modules\Assets;

use App\Modules\Assets\Adapters\CustodyClearance;
use App\Modules\Assets\Enums\AssetsFeature;
use App\Modules\Hr\Ports\OffboardingClearance;
use App\Modules\Platform\Access\FeatureRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Fixed assets and custody. Uses HR's StaffDirectory for holders and plugs a custody check into
 * HR's OffboardingClearance; never HR's tables.
 */
class AssetsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->app->make(FeatureRegistry::class)->register(AssetsFeature::class);
        $this->app->make(OffboardingClearance::class)->register(new CustodyClearance);
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}
