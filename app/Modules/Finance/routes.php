<?php

use App\Modules\Finance\Http\Controllers\LedgerController;
use App\Modules\Finance\Http\Controllers\PurchasingController;
use App\Modules\Finance\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

/*
| Finance pages live under /suite/{scope}/finance. They follow the package features
| finance.accounting (ledger, reports) and finance.purchasing (suppliers, orders, bills)
| rather than a module subscription.
*/
Route::middleware(['web', 'auth', 'password.changed', 'suite.scope'])
    ->prefix('suite/{scope}/finance')->where(['scope' => 'all|[oc]-[a-z0-9-]+'])->name('finance.')
    ->group(function (): void {
        Route::middleware('feature:finance.accounting')->group(function (): void {
            Route::post('/accounts', [LedgerController::class, 'storeAccount'])->middleware('access:finance.ledger.update')->name('accounts.store');
            Route::put('/accounts/{account}', [LedgerController::class, 'updateAccount'])->middleware('access:finance.ledger.update')->name('accounts.update');
            Route::post('/journal', [LedgerController::class, 'storeEntry'])->middleware('access:finance.ledger.create')->name('journal.store');
            Route::get('/ledger', [LedgerController::class, 'index'])->middleware('access:finance.ledger.view')->name('ledger');
            Route::get('/reports', ReportController::class)->middleware('access:finance.reports.view')->name('reports');
        });

        Route::middleware('feature:finance.purchasing')->group(function (): void {
            Route::post('/suppliers', [PurchasingController::class, 'storeSupplier'])->middleware('access:finance.purchasing.create|finance.payables.create')->name('suppliers.store');
            Route::put('/suppliers/{supplier}', [PurchasingController::class, 'updateSupplier'])->middleware('access:finance.purchasing.create|finance.payables.create')->name('suppliers.update');
            Route::post('/orders', [PurchasingController::class, 'storeOrder'])->middleware('access:finance.purchasing.create')->name('orders.store');
            Route::put('/orders/{order}/status', [PurchasingController::class, 'moveOrder'])->middleware('access:finance.purchasing.create|finance.purchasing.approve|finance.payables.create')->name('orders.move');
            Route::post('/bills', [PurchasingController::class, 'storeBill'])->middleware('access:finance.payables.create')->name('bills.store');
            Route::put('/bills/{bill}/approve', [PurchasingController::class, 'approveBill'])->middleware('access:finance.payables.approve')->name('bills.approve');
            Route::post('/bills/{bill}/payments', [PurchasingController::class, 'payBill'])->middleware('access:finance.payables.pay')->name('bills.pay');
            Route::get('/purchasing', [PurchasingController::class, 'index'])->middleware('access:finance.purchasing.view|finance.payables.view')->name('purchasing');
        });
    });
