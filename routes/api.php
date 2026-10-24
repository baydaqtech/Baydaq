<?php

use App\Http\Controllers\Api\UsageController;
use App\Http\Middleware\AuthenticateServiceKey;
use Illuminate\Support\Facades\Route;

/*
| Mobile / integration API, versioned. Each module mounts its own routes under /api/v1 from its
| routes/api.php. Authentication is a Sanctum token issued at sign-in and bound to one device.
*/
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    // Technical services report delivered units for a client's project with the service key.
    Route::post('/usage', [UsageController::class, 'store'])
        ->middleware([AuthenticateServiceKey::class, 'throttle:120,1'])->name('usage.store');

    foreach (glob(app_path('Modules/*/routes/api.php')) as $file) {
        require $file;
    }
});
