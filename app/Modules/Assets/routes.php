<?php

use App\Modules\Assets\Http\Controllers\AssetController;
use App\Modules\Assets\Http\Controllers\AuditController;
use App\Modules\Assets\Http\Controllers\CategoryController;
use App\Modules\Assets\Http\Controllers\MaintenanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'password.changed', 'suite.scope', 'module:assets'])
    ->prefix('suite/{scope}/assets')->where(['scope' => 'all|[oc]-[a-z0-9-]+'])->name('assets.')
    ->group(function (): void {
        Route::middleware('access:assets.register.create|assets.register.update')->group(function (): void {
            Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        });
        Route::get('/categories', [CategoryController::class, 'index'])->middleware('access:assets.register.view')->name('categories.index');

        Route::get('/audits', [AuditController::class, 'index'])->middleware('access:assets.audits.view')->name('audits.index');
        Route::post('/audits', [AuditController::class, 'store'])->middleware('access:assets.audits.create')->name('audits.store');
        Route::get('/audits/{audit}', [AuditController::class, 'show'])->middleware('access:assets.audits.view')->name('audits.show');
        Route::post('/audits/{audit}/scan', [AuditController::class, 'scan'])->middleware('access:assets.audits.create')->name('audits.scan');
        Route::post('/audits/{audit}/close', [AuditController::class, 'close'])->middleware('access:assets.audits.close')->name('audits.close');

        Route::post('/assets/{asset}/maintenance', [MaintenanceController::class, 'store'])->middleware('access:assets.maintenance.create')->name('maintenance.store');
        Route::put('/maintenance/{job}/close', [MaintenanceController::class, 'close'])->middleware('access:assets.maintenance.update')->name('maintenance.close');

        Route::post('/assets/{asset}/custody', [AssetController::class, 'assign'])->middleware('access:assets.register.assign')->name('custody.assign');
        Route::put('/custody/{custody}/return', [AssetController::class, 'return'])->middleware('access:assets.register.assign')->name('custody.return');
        Route::post('/assets/{asset}/dispose', [AssetController::class, 'dispose'])->middleware('access:assets.register.dispose')->name('dispose');

        Route::post('/', [AssetController::class, 'store'])->middleware('access:assets.register.create')->name('store');
        Route::put('/assets/{asset}', [AssetController::class, 'update'])->middleware('access:assets.register.update')->name('update');
        Route::get('/assets/{asset}', [AssetController::class, 'show'])->middleware('access:assets.register.view')->name('show');
        Route::get('/', [AssetController::class, 'index'])->middleware('access:assets.register.view')->name('index');
    });
