<?php

namespace App\Modules\Reservations;

use App\Modules\Platform\Access\FeatureRegistry;
use App\Modules\Platform\Events\PaymentSucceeded;
use App\Modules\Platform\Ports\CustomerActivity;
use App\Modules\Platform\Ports\CustomerData;
use App\Modules\Reservations\Adapters\BookingCustomerActivity;
use App\Modules\Reservations\Adapters\BookingCustomerData;
use App\Modules\Reservations\Console\SendBookingReminders;
use App\Modules\Reservations\Enums\ReservationsFeature;
use App\Modules\Reservations\Listeners\ConfirmPaidDeposit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Bookings for services, resources and staff. Asks HR's StaffAvailability who can be booked and
 * Invoicing's InvoiceIssuer for checkout invoices; never their tables.
 */
class ReservationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([BookingCustomerActivity::class], CustomerActivity::TAG);
        $this->app->tag([BookingCustomerData::class], CustomerData::TAG);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->app->make(FeatureRegistry::class)->register(ReservationsFeature::class);
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        Event::listen(PaymentSucceeded::class, ConfirmPaidDeposit::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SendBookingReminders::class]);
        }
    }
}
