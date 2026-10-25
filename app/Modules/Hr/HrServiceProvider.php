<?php

namespace App\Modules\Hr;

use App\Modules\Hr\Adapters\EmployeePayrollAdjustments;
use App\Modules\Hr\Adapters\EmployeeStaffAvailability;
use App\Modules\Hr\Adapters\EmployeeStaffDirectory;
use App\Modules\Hr\Adapters\EmployeeStaffPresence;
use App\Modules\Hr\Adapters\EmployeeSuiteSearch;
use App\Modules\Hr\Adapters\HrBoardSignals;
use App\Modules\Hr\Console\ApplyMovements;
use App\Modules\Hr\Console\NotifyExpiringDocuments;
use App\Modules\Hr\Console\RechargeSecondmentsCommand;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Ports\OffboardingClearance;
use App\Modules\Hr\Ports\PayrollAdjustments;
use App\Modules\Hr\Ports\StaffAvailability;
use App\Modules\Hr\Ports\StaffDirectory;
use App\Modules\Hr\Ports\StaffPresence;
use App\Modules\Platform\Access\FeatureRegistry;
use App\Modules\Platform\Ports\BoardSignals;
use App\Modules\Platform\Ports\SuiteSearch;
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
        $this->app->bind(PayrollAdjustments::class, EmployeePayrollAdjustments::class);
        $this->app->bind(StaffPresence::class, EmployeeStaffPresence::class);
        $this->app->tag([EmployeeSuiteSearch::class], SuiteSearch::TAG);
        $this->app->tag([HrBoardSignals::class], BoardSignals::TAG);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->app->make(FeatureRegistry::class)->register(HrFeature::class);

        if (file_exists(__DIR__.'/routes.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([ApplyMovements::class, NotifyExpiringDocuments::class, RechargeSecondmentsCommand::class]);
        }
    }
}
