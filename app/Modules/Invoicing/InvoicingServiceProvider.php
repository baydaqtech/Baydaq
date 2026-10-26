<?php

namespace App\Modules\Invoicing;

use App\Modules\Invoicing\Adapters\ZatcaInvoiceIssuer;
use App\Modules\Invoicing\Enums\InvoicingFeature;
use App\Modules\Invoicing\Listeners\MarkInvoicePaid;
use App\Modules\Invoicing\Ports\InvoiceIssuer;
use App\Modules\Invoicing\Zatca\ZatcaClient;
use App\Modules\Platform\Access\FeatureRegistry;
use App\Modules\Platform\Events\PaymentSucceeded;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Tenant invoicing with ZATCA Phase 2 e-invoicing. Other modules issue invoices through its port;
 * nobody else touches the inv__ tables.
 */
class InvoicingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ZatcaClient::class);
        $this->app->bind(InvoiceIssuer::class, ZatcaInvoiceIssuer::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'invoicing');
        $this->app->make(FeatureRegistry::class)->register(InvoicingFeature::class);
        Event::listen(PaymentSucceeded::class, MarkInvoicePaid::class);

        if (file_exists(__DIR__.'/routes.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes.php');
        }
    }
}
