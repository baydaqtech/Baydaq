<?php

use App\Modules\Invoicing\Http\Controllers\CustomerController;
use App\Modules\Invoicing\Http\Controllers\InvoiceController;
use App\Modules\Invoicing\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'password.changed', 'suite.scope', 'module:invoicing'])
    ->prefix('suite/{scope}/invoicing')->where(['scope' => 'all|[oc]-[a-z0-9-]+'])->name('invoicing.')
    ->group(function (): void {
        Route::post('/customers', [CustomerController::class, 'store'])->middleware('access:invoicing.customers.create')->name('customers.store');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware('access:invoicing.customers.update')->name('customers.update');
        Route::get('/customers', [CustomerController::class, 'index'])->middleware('access:invoicing.customers.view|invoicing.customers.create|invoicing.customers.update')->name('customers.index');

        Route::middleware('access:invoicing.invoices.create')->group(function (): void {
            Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
            Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        });
        Route::middleware('access:invoicing.invoices.update')->group(function (): void {
            Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
            Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
        });
        Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->middleware('access:invoicing.invoices.issue')->name('invoices.issue');
        Route::post('/invoices/{invoice}/payment-link', [InvoiceController::class, 'paymentLink'])->middleware('access:invoicing.invoices.collect')->name('invoices.payment_link');
        Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->middleware('access:invoicing.invoices.cancel')->name('invoices.cancel');
        Route::get('/invoices/{invoice}/xml', [InvoiceController::class, 'xml'])->middleware('access:invoicing.invoices.export')->name('invoices.xml');
        Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->middleware('access:invoicing.invoices.view')->name('invoices.pdf');
        Route::middleware('access:invoicing.invoices.view|invoicing.invoices.create')->group(function (): void {
            Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
            Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        });

        Route::post('/units', [UnitController::class, 'store'])->middleware('access:invoicing.einvoicing.onboard')->name('units.store');
        Route::post('/units/{unit}/compliance', [UnitController::class, 'compliance'])->middleware('access:invoicing.einvoicing.onboard')->name('units.compliance');
        Route::post('/units/{unit}/production', [UnitController::class, 'production'])->middleware('access:invoicing.einvoicing.onboard')->name('units.production');
        Route::get('/units', [UnitController::class, 'index'])->middleware('access:invoicing.einvoicing.view|invoicing.einvoicing.onboard')->name('units.index');
    });
