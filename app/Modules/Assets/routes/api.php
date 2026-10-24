<?php

use App\Modules\Assets\Http\Controllers\Api\InventoryController;
use Illuminate\Support\Facades\Route;

/*
| Employee app: inventory in my hands, scanning, handovers and incident reports.
*/
Route::middleware(['auth:sanctum', 'api.tenant'])->prefix('inventory')->name('inventory.')->group(function (): void {
    Route::get('/holdings', [InventoryController::class, 'holdings'])->name('holdings');
    Route::get('/lookup', [InventoryController::class, 'lookup'])->middleware('throttle:120,1')->name('lookup');
    Route::get('/recipients', [InventoryController::class, 'recipients'])->name('recipients');
    Route::get('/handovers', [InventoryController::class, 'handovers'])->name('handovers.index');
    Route::post('/handovers', [InventoryController::class, 'store'])->middleware('throttle:30,1')->name('handovers.store');
    Route::get('/handovers/{handover}', [InventoryController::class, 'show'])->name('handovers.show');
    Route::post('/handovers/{handover}/accept', [InventoryController::class, 'accept'])->name('handovers.accept');
    Route::post('/handovers/{handover}/reject', [InventoryController::class, 'reject'])->name('handovers.reject');
    Route::post('/incidents', [InventoryController::class, 'report'])->middleware('throttle:30,1')->name('incidents.store');
});
