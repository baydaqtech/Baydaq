<?php

use App\Http\Controllers\Admin\ClientController as AdminClientController;
use App\Http\Controllers\Admin\ClientUserController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InvoiceController as AdminInvoiceController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProjectController as AdminProjectController;
use App\Http\Controllers\Admin\ProjectItemsController;
use App\Http\Controllers\Admin\ProjectServiceController;
use App\Http\Controllers\Admin\RequestController as AdminRequestController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Auth\AccountSetupController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Portal\HomeController;
use App\Http\Controllers\Portal\ProjectController as PortalProjectController;
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

        // Each area: viewing needs the view or manage permission, changes need manage.
        Route::middleware('permission:clients.manage')->group(function () {
            Route::get('/clients/create', [AdminClientController::class, 'create'])->name('clients.create');
            Route::post('/clients', [AdminClientController::class, 'store'])->name('clients.store');
            Route::get('/clients/{client}/edit', [AdminClientController::class, 'edit'])->name('clients.edit');
            Route::put('/clients/{client}', [AdminClientController::class, 'update'])->name('clients.update');
            Route::post('/clients/{client}/users', [ClientUserController::class, 'store'])->name('clients.users.store');
            Route::post('/clients/{client}/users/{user}/invite', [ClientUserController::class, 'resendInvite'])->name('clients.users.invite');
        });
        Route::middleware('permission:clients.view|clients.manage')->group(function () {
            Route::get('/clients', [AdminClientController::class, 'index'])->name('clients.index');
            Route::get('/clients/{client}', [AdminClientController::class, 'show'])->name('clients.show');
        });

        Route::middleware('permission:projects.manage')->group(function () {
            Route::get('/projects/create', [AdminProjectController::class, 'create'])->name('projects.create');
            Route::post('/projects', [AdminProjectController::class, 'store'])->name('projects.store');
            Route::get('/projects/{project}/edit', [AdminProjectController::class, 'edit'])->name('projects.edit');
            Route::put('/projects/{project}', [AdminProjectController::class, 'update'])->name('projects.update');
            Route::put('/projects/{project}/milestones', [ProjectItemsController::class, 'milestones'])->name('projects.milestones');
            Route::put('/projects/{project}/resources', [ProjectItemsController::class, 'resources'])->name('projects.resources');
            Route::put('/projects/{project}/files', [ProjectItemsController::class, 'files'])->name('projects.files');
            Route::post('/projects/{project}/services', [ProjectServiceController::class, 'store'])->name('projects.services.store');
            Route::put('/projects/{project}/services/{projectService}', [ProjectServiceController::class, 'update'])->name('projects.services.update');
        });
        Route::middleware('permission:projects.view|projects.manage')->group(function () {
            Route::get('/projects', [AdminProjectController::class, 'index'])->name('projects.index');
            Route::get('/projects/{project}', [AdminProjectController::class, 'show'])->name('projects.show');
        });

        Route::middleware('permission:invoices.manage')->group(function () {
            Route::get('/invoices/create', [AdminInvoiceController::class, 'create'])->name('invoices.create');
            Route::post('/invoices', [AdminInvoiceController::class, 'store'])->name('invoices.store');
            Route::get('/invoices/{invoice}/edit', [AdminInvoiceController::class, 'edit'])->name('invoices.edit');
            Route::put('/invoices/{invoice}', [AdminInvoiceController::class, 'update'])->name('invoices.update');
            Route::post('/invoices/{invoice}/send', [AdminInvoiceController::class, 'send'])->name('invoices.send');
            Route::post('/invoices/{invoice}/pay', [AdminInvoiceController::class, 'pay'])->name('invoices.pay');
            Route::post('/invoices/{invoice}/void', [AdminInvoiceController::class, 'void'])->name('invoices.void');
        });
        Route::middleware('permission:invoices.view|invoices.manage')->group(function () {
            Route::get('/invoices', [AdminInvoiceController::class, 'index'])->name('invoices.index');
            Route::get('/invoices/{invoice}', [AdminInvoiceController::class, 'show'])->name('invoices.show');
            Route::get('/invoices/{invoice}/pdf', InvoicePdfController::class)->name('invoices.pdf');
        });

        Route::middleware('permission:requests.manage')->group(function () {
            Route::get('/requests', [AdminRequestController::class, 'index'])->name('requests.index');
            Route::get('/requests/{clientRequest}', [AdminRequestController::class, 'show'])->name('requests.show');
            Route::put('/requests/{clientRequest}', [AdminRequestController::class, 'update'])->name('requests.update');
            Route::post('/requests/{clientRequest}/approve', [AdminRequestController::class, 'approve'])->name('requests.approve');
            Route::post('/requests/{clientRequest}/reject', [AdminRequestController::class, 'reject'])->name('requests.reject');
        });

        Route::middleware('permission:services.manage')->group(function () {
            Route::get('/services', [AdminServiceController::class, 'index'])->name('services.index');
            Route::get('/services/create', [AdminServiceController::class, 'create'])->name('services.create');
            Route::post('/services', [AdminServiceController::class, 'store'])->name('services.store');
            Route::get('/services/{service}/edit', [AdminServiceController::class, 'edit'])->name('services.edit');
            Route::put('/services/{service}', [AdminServiceController::class, 'update'])->name('services.update');

            Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
            Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
            Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
            Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
            Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
        });

        Route::middleware('permission:admins.manage')->group(function () {
            Route::get('/team', [TeamController::class, 'index'])->name('team.index');
            Route::get('/team/create', [TeamController::class, 'create'])->name('team.create');
            Route::post('/team', [TeamController::class, 'store'])->name('team.store');
            Route::get('/team/{member}/edit', [TeamController::class, 'edit'])->name('team.edit');
            Route::put('/team/{member}', [TeamController::class, 'update'])->name('team.update');
            Route::post('/team/{member}/invite', [TeamController::class, 'resendInvite'])->name('team.invite');
        });

        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        });
    });

Route::prefix('app')
    ->name('portal.')
    ->middleware(['auth', 'password.changed', 'role:client'])
    ->group(function () {
        Route::get('/', HomeController::class)->name('home');
        Route::get('/projects/{project}', [PortalProjectController::class, 'show'])->name('projects.show');
        Route::get('/invoices/{invoice}/pdf', InvoicePdfController::class)->name('invoices.pdf');
    });
