<?php

namespace App\Modules\Platform;

use App\Modules\Platform\Access\FeatureRegistry;
use App\Modules\Platform\Adapters\AuditTrail;
use App\Modules\Platform\Adapters\ChillerlanQrCodes;
use App\Modules\Platform\Adapters\ClaudeIntelligence;
use App\Modules\Platform\Adapters\CustomerSuiteSearch;
use App\Modules\Platform\Adapters\GovernmentCalendar;
use App\Modules\Platform\Adapters\HostedPayments;
use App\Modules\Platform\Adapters\MpdfRenderer;
use App\Modules\Platform\Adapters\NullLedger;
use App\Modules\Platform\Adapters\ResolvedAccess;
use App\Modules\Platform\Adapters\SubscriptionEntitlements;
use App\Modules\Platform\Adapters\TenantAccounts;
use App\Modules\Platform\Adapters\TenantOrgDirectory;
use App\Modules\Platform\Adapters\ThemeBranding;
use App\Modules\Platform\Adapters\WalletCustomers;
use App\Modules\Platform\Adapters\WhatsAppMessaging;
use App\Modules\Platform\Enums\SuiteFeature;
use App\Modules\Platform\Ports\Access;
use App\Modules\Platform\Ports\Accounts;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\Branding;
use App\Modules\Platform\Ports\CalendarSource;
use App\Modules\Platform\Ports\Customers;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Ports\Intelligence;
use App\Modules\Platform\Ports\Ledger;
use App\Modules\Platform\Ports\Messaging;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Platform\Ports\Payments;
use App\Modules\Platform\Ports\PdfRenderer;
use App\Modules\Platform\Ports\QrCodes;
use App\Modules\Platform\Ports\SuiteSearch;
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
        $this->app->bind(Messaging::class, WhatsAppMessaging::class);
        $this->app->bind(Payments::class, HostedPayments::class);
        $this->app->bind(Customers::class, WalletCustomers::class);
        $this->app->bind(QrCodes::class, ChillerlanQrCodes::class);
        $this->app->bind(PdfRenderer::class, MpdfRenderer::class);
        $this->app->bind(Branding::class, ThemeBranding::class);
        $this->app->bindIf(Ledger::class, NullLedger::class);
        $this->app->bind(Intelligence::class, fn (): Intelligence => new ClaudeIntelligence(config('baydaq.ai.api_key'), (string) config('baydaq.ai.model')));
        $this->app->tag([CustomerSuiteSearch::class], SuiteSearch::TAG);
        $this->app->tag([GovernmentCalendar::class], CalendarSource::TAG);
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
