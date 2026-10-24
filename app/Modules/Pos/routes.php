<?php

use App\Modules\Pos\Http\Controllers\CatalogController;
use App\Modules\Pos\Http\Controllers\MenuController;
use App\Modules\Pos\Http\Controllers\OrderController;
use App\Modules\Pos\Http\Controllers\RegisterController;
use App\Modules\Pos\Http\Controllers\SellController;
use Illuminate\Support\Facades\Route;

// Public QR menu (no login; the token names the register).
Route::middleware(['web'])->group(function (): void {
    Route::get('/menu/{token}', [MenuController::class, 'show'])->where('token', '[A-Za-z0-9]{32}')->name('menu.show');
    Route::post('/menu/{token}/order', [MenuController::class, 'order'])->where('token', '[A-Za-z0-9]{32}')->middleware('throttle:20,1')->name('menu.order');
});

Route::middleware(['web', 'auth', 'password.changed', 'suite.scope', 'module:pos'])
    ->prefix('suite/{scope}/pos')->where(['scope' => 'all|[oc]-[a-z0-9-]+'])->name('pos.')
    ->group(function (): void {
        Route::middleware('access:pos.catalog.create|pos.catalog.update')->group(function (): void {
            Route::post('/catalog/categories', [CatalogController::class, 'storeCategory'])->name('catalog.categories.store');
            Route::put('/catalog/categories/{category}', [CatalogController::class, 'updateCategory'])->name('catalog.categories.update');
            Route::post('/catalog/products', [CatalogController::class, 'storeProduct'])->name('catalog.products.store');
            Route::put('/catalog/products/{product}', [CatalogController::class, 'updateProduct'])->name('catalog.products.update');
            Route::post('/catalog/groups', [CatalogController::class, 'storeGroup'])->name('catalog.groups.store');
            Route::put('/catalog/groups/{group}', [CatalogController::class, 'updateGroup'])->name('catalog.groups.update');
        });
        Route::get('/catalog', [CatalogController::class, 'index'])->middleware('access:pos.catalog.view|pos.catalog.create|pos.catalog.update')->name('catalog.index');

        Route::middleware('access:pos.registers.create|pos.registers.update')->group(function (): void {
            Route::post('/registers', [RegisterController::class, 'store'])->name('registers.store');
            Route::put('/registers/{register}', [RegisterController::class, 'update'])->name('registers.update');
            Route::post('/cashiers', [RegisterController::class, 'storeCashier'])->name('cashiers.store');
            Route::put('/cashiers/{cashier}', [RegisterController::class, 'updateCashier'])->name('cashiers.update');
        });
        Route::post('/registers/{register}/shifts', [RegisterController::class, 'openShift'])->middleware('access:pos.registers.open_shift')->name('shifts.open');
        Route::put('/shifts/{shift}/close', [RegisterController::class, 'closeShift'])->middleware('access:pos.registers.close_shift')->name('shifts.close');
        Route::get('/registers', [RegisterController::class, 'index'])->middleware('access:pos.registers.view|pos.registers.create|pos.registers.open_shift')->name('registers.index');

        Route::middleware('access:pos.orders.sell')->group(function (): void {
            Route::get('/sell/{register}', [SellController::class, 'show'])->name('sell');
            Route::post('/sell/{register}/orders', [SellController::class, 'store'])->name('sell.orders.store');
            Route::post('/sell/{register}/orders/{order}/pay', [SellController::class, 'pay'])->name('sell.orders.pay');
        });
        Route::post('/sell/{register}/orders/{order}/void', [SellController::class, 'void'])->middleware('access:pos.orders.void')->name('sell.orders.void');
        Route::put('/orders/{order}/kitchen', [SellController::class, 'kitchen'])->middleware('access:pos.orders.kitchen')->name('orders.kitchen');
        Route::get('/kitchen', [OrderController::class, 'kitchen'])->middleware('access:pos.orders.kitchen')->name('kitchen');
        Route::get('/orders', [OrderController::class, 'index'])->middleware('access:pos.orders.view|pos.orders.sell')->name('orders.index');
    });
