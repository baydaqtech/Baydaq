<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Auth\AccountSetupController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Portal\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\ServiceCatalogController;
use Illuminate\Support\Facades\Route;

/*
| Public site.
*/
Route::get('/', LandingController::class)->name('home');
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/services', [ServiceCatalogController::class, 'index'])->name('services.index');
Route::get('/request/{type}', [RequestController::class, 'create'])->name('requests.create');
Route::post('/request/{type}', [RequestController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('requests.store');

Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('contact.store');

/*
| Accounts: no sign-up. Login, logout and forgot/reset password come from Fortify;
| new users set their first password from the "your account is ready" email.
*/
Route::middleware('guest')->group(function () {
    Route::get('/account/setup/{token}', [AccountSetupController::class, 'show'])->name('account.setup');
    Route::post('/account/setup', [AccountSetupController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('account.setup.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/password/change', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::put('/password/change', [PasswordChangeController::class, 'update'])->name('password.change.update');
});

/*
| Admin (team) and client portal.
*/
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'password.changed', 'role:system_admin|admin'])
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
    });

Route::prefix('app')
    ->name('portal.')
    ->middleware(['auth', 'password.changed', 'role:client'])
    ->group(function () {
        Route::get('/', HomeController::class)->name('home');
    });
