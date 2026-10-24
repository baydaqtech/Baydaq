<?php

use App\Modules\Assets\AssetsServiceProvider;
use App\Modules\Hr\HrServiceProvider;
use App\Modules\Invoicing\InvoicingServiceProvider;
use App\Modules\Platform\PlatformServiceProvider;
use App\Modules\Pos\PosServiceProvider;
use App\Modules\Reservations\ReservationsServiceProvider;
use App\Modules\Subscriptions\SubscriptionsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    PlatformServiceProvider::class,
    HrServiceProvider::class,
    InvoicingServiceProvider::class,
    PosServiceProvider::class,
    ReservationsServiceProvider::class,
    SubscriptionsServiceProvider::class,
    AssetsServiceProvider::class,
];
