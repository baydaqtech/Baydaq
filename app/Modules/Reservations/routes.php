<?php

use App\Modules\Reservations\Http\Controllers\BookingController;
use App\Modules\Reservations\Http\Controllers\CalendarController;
use App\Modules\Reservations\Http\Controllers\CatalogController;
use App\Modules\Reservations\Http\Controllers\PublicBookingController;
use Illuminate\Support\Facades\Route;

// Public booking page (no login; the token names the branch).
Route::middleware(['web'])->group(function (): void {
    Route::get('/book/{token}', [PublicBookingController::class, 'show'])->where('token', '[A-Za-z0-9]{32}')->name('book.show');
    Route::post('/book/{token}', [PublicBookingController::class, 'store'])->where('token', '[A-Za-z0-9]{32}')->middleware('throttle:10,1')->name('book.store');
});

Route::middleware(['web', 'auth', 'password.changed', 'suite.scope', 'module:reservations'])
    ->prefix('suite/{scope}/reservations')->where(['scope' => 'all|[oc]-[a-z0-9-]+'])->name('reservations.')
    ->group(function (): void {
        Route::middleware('access:reservations.catalog.create|reservations.catalog.update')->group(function (): void {
            Route::post('/catalog/services', [CatalogController::class, 'storeService'])->name('catalog.services.store');
            Route::put('/catalog/services/{service}', [CatalogController::class, 'updateService'])->name('catalog.services.update');
            Route::post('/catalog/resources', [CatalogController::class, 'storeResource'])->name('catalog.resources.store');
            Route::put('/catalog/resources/{resource}', [CatalogController::class, 'updateResource'])->name('catalog.resources.update');
            Route::put('/catalog/branches/{branch}', [CatalogController::class, 'updateSettings'])->whereNumber('branch')->name('catalog.settings.update');
        });
        Route::get('/catalog', [CatalogController::class, 'index'])->middleware('access:reservations.catalog.view|reservations.catalog.create|reservations.catalog.update')->name('catalog.index');

        Route::post('/bookings', [CalendarController::class, 'store'])->middleware('access:reservations.bookings.create')->name('bookings.store');
        Route::put('/bookings/{booking}/status', [CalendarController::class, 'status'])->middleware('access:reservations.bookings.update|reservations.bookings.cancel')->name('bookings.status');
        Route::put('/bookings/{booking}/deposit', [CalendarController::class, 'deposit'])->middleware('access:reservations.bookings.update')->name('bookings.deposit');
        Route::post('/bookings/{booking}/checkout', [CalendarController::class, 'checkout'])->middleware('access:reservations.bookings.checkout')->name('bookings.checkout');
        Route::get('/bookings', [BookingController::class, 'index'])->middleware('access:reservations.bookings.view')->name('bookings.index');
        Route::get('/calendar', [CalendarController::class, 'show'])->middleware('access:reservations.bookings.view|reservations.bookings.create')->name('calendar');
    });
