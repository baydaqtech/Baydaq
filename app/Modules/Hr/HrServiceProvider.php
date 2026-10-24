<?php

namespace App\Modules\Hr;

use App\Modules\Hr\Adapters\EmployeeStaffAvailability;
use App\Modules\Hr\Adapters\EmployeeStaffDirectory;
use App\Modules\Hr\Console\ApplyMovements;
use App\Modules\Hr\Console\NotifyExpiringDocuments;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Ports\OffboardingClearance;
use App\Modules\Hr\Ports\StaffAvailability;
use App\Modules\Hr\Ports\StaffDirectory;
use App\Modules\Platform\Access\FeatureRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * The HR module: people, contracts, documents and movements. Talks to the kernel through its
 * ports and offers StaffDirectory, StaffAvailability and the OffboardingClearance extension
 * point to the other modules.
 */
class HrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StaffDirectory::class, EmployeeStaffDirectory::class);
        $this->app->bind(StaffAvailability::class, EmployeeStaffAvailability::class);
        $this->app->singleton(OffboardingClearance::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->app->make(FeatureRegistry::class)->register(HrFeature::class);

        if (file_exists(__DIR__.'/routes.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([ApplyMovements::class, NotifyExpiringDocuments::class]);
        }
    }
}
