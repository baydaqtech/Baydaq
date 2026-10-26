<?php

use App\Modules\Assets\Http\Controllers\AssetController;
use App\Modules\Assets\Http\Controllers\AuditController;
use App\Modules\Assets\Http\Controllers\CategoryController;
use App\Modules\Assets\Http\Controllers\Inventory\CatalogController as InventoryCatalogController;
use App\Modules\Assets\Http\Controllers\Inventory\HandoverController;
use App\Modules\Assets\Http\Controllers\Inventory\IncidentController;
use App\Modules\Assets\Http\Controllers\Inventory\TransferController;
use App\Modules\Assets\Http\Controllers\Inventory\WarehouseController;
use App\Modules\Assets\Http\Controllers\MaintenanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'password.changed', 'suite.scope', 'module:assets'])
    ->prefix('suite/{scope}/assets')->where(['scope' => 'all|[oc]-[a-z0-9-]+'])->name('assets.')
    ->group(function (): void {
        // Inventory: stock items, warehouses, transfers, handovers and incidents.
        Route::prefix('inventory')->name('inventory.')->group(function (): void {
            Route::middleware('access:assets.inventory.create|assets.inventory.update')->group(function (): void {
                Route::post('/items', [InventoryCatalogController::class, 'storeItem'])->name('items.store');
                Route::put('/items/{item}', [InventoryCatalogController::class, 'updateItem'])->name('items.update');
                Route::post('/units', [InventoryCatalogController::class, 'storeUnit'])->name('units.store');
                Route::put('/units/{unit}', [InventoryCatalogController::class, 'updateUnit'])->name('units.update');
                Route::post('/warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
                Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
                Route::post('/warehouses/{warehouse}/receive', [WarehouseController::class, 'receive'])->name('warehouses.receive');
            });
            Route::get('/items', [InventoryCatalogController::class, 'index'])->middleware('access:assets.inventory.view')->name('items.index');
            Route::get('/warehouses', [WarehouseController::class, 'index'])->middleware('access:assets.inventory.view')->name('warehouses.index');
            Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show'])->middleware('access:assets.inventory.view')->name('warehouses.show');

            Route::post('/transfers', [TransferController::class, 'store'])->middleware('access:assets.transfers.create')->name('transfers.store');
            Route::post('/transfers/{transfer}/send', [TransferController::class, 'send'])->middleware('access:assets.transfers.send')->name('transfers.send');
            Route::post('/transfers/{transfer}/receive', [TransferController::class, 'receive'])->middleware('access:assets.transfers.receive')->name('transfers.receive');
            Route::post('/transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->middleware('access:assets.transfers.create')->name('transfers.cancel');
            Route::get('/transfers', [TransferController::class, 'index'])->middleware('access:assets.transfers.view|assets.transfers.create')->name('transfers.index');
            Route::get('/transfers/{transfer}', [TransferController::class, 'show'])->middleware('access:assets.transfers.view|assets.transfers.create|assets.transfers.receive')->name('transfers.show');

            // Receivers and senders are checked per handover in the controller.
            Route::post('/handovers', [HandoverController::class, 'store'])->name('handovers.store');
            Route::post('/handovers/{handover}/accept', [HandoverController::class, 'accept'])->name('handovers.accept');
            Route::post('/handovers/{handover}/reject', [HandoverController::class, 'reject'])->name('handovers.reject');
            Route::get('/handovers', [HandoverController::class, 'index'])->middleware('access:assets.handovers.view|assets.handovers.create')->name('handovers.index');
            Route::get('/handovers/{handover}', [HandoverController::class, 'show'])->name('handovers.show');

            Route::post('/incidents', [IncidentController::class, 'store'])->middleware('access:assets.incidents.create')->name('incidents.store');
            Route::put('/incidents/{incident}/resolve', [IncidentController::class, 'resolve'])->middleware('access:assets.incidents.resolve')->name('incidents.resolve');
            Route::get('/incidents', [IncidentController::class, 'index'])->middleware('access:assets.incidents.view|assets.incidents.resolve')->name('incidents.index');
        });

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
