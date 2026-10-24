<?php

use App\Modules\Platform\Http\Controllers\AccessRoleController;
use App\Modules\Platform\Http\Controllers\BranchController;
use App\Modules\Platform\Http\Controllers\BrandController;
use App\Modules\Platform\Http\Controllers\CompanyController;
use App\Modules\Platform\Http\Controllers\HomeController;
use App\Modules\Platform\Http\Controllers\MemberController;
use App\Modules\Platform\Http\Controllers\OrganizationController;
use App\Modules\Platform\Http\Controllers\StructureController;
use App\Modules\Platform\Http\Controllers\SuiteEntryController;
use App\Modules\Platform\Http\Controllers\WorkLocationController;
use Illuminate\Support\Facades\Route;

/*
| The business suite. Every page lives under /suite/{scope}, where {scope} is "all",
| "o-{organization slug}" or "c-{company slug}". Modules mount their own routes under the
| same prefix from their routes.php with the same middleware plus module:{name}.
| Within a feature, the change routes come before the view routes.
*/
Route::middleware(['web', 'auth', 'password.changed'])->prefix('suite')->name('suite.')->group(function (): void {
    Route::get('/', SuiteEntryController::class)->name('entry');

    Route::prefix('{scope}')->where(['scope' => 'all|[oc]-[a-z0-9-]+'])->middleware('suite.scope')->group(function (): void {
        Route::get('/', HomeController::class)->name('home');

        // Structure: organizations, companies and branches.
        Route::middleware('access:suite.structure.create')->group(function (): void {
            Route::post('/structure/organizations', [OrganizationController::class, 'store'])->name('structure.organizations.store');
            Route::get('/structure/companies/create', [CompanyController::class, 'create'])->name('structure.companies.create');
            Route::post('/structure/companies', [CompanyController::class, 'store'])->name('structure.companies.store');
            Route::get('/structure/companies/{company}/branches/create', [BranchController::class, 'create'])->name('structure.branches.create');
            Route::post('/structure/companies/{company}/branches', [BranchController::class, 'store'])->name('structure.branches.store');
        });
        Route::middleware('access:suite.structure.update')->group(function (): void {
            Route::put('/structure/organizations/{organization}', [OrganizationController::class, 'update'])->name('structure.organizations.update');
            Route::get('/structure/companies/{company}/edit', [CompanyController::class, 'edit'])->name('structure.companies.edit');
            Route::put('/structure/companies/{company}', [CompanyController::class, 'update'])->name('structure.companies.update');
            Route::get('/structure/branches/{branch}/edit', [BranchController::class, 'edit'])->name('structure.branches.edit');
            Route::put('/structure/branches/{branch}', [BranchController::class, 'update'])->name('structure.branches.update');
        });
        Route::post('/structure/brands', [BrandController::class, 'store'])->middleware('access:suite.structure.create')->name('structure.brands.store');
        Route::put('/structure/brands/{brand}', [BrandController::class, 'update'])->middleware('access:suite.structure.update')->name('structure.brands.update');
        Route::get('/structure/brands', [BrandController::class, 'index'])->middleware('access:suite.structure.view|suite.structure.create|suite.structure.update')->name('structure.brands.index');
        Route::post('/structure/locations', [WorkLocationController::class, 'store'])->middleware('access:suite.structure.create')->name('structure.locations.store');
        Route::put('/structure/locations/{location}', [WorkLocationController::class, 'update'])->middleware('access:suite.structure.update')->name('structure.locations.update');
        Route::get('/structure/locations', [WorkLocationController::class, 'index'])
            ->middleware('access:suite.structure.view|suite.structure.create|suite.structure.update')->name('structure.locations.index');
        Route::delete('/structure/branches/{branch}', [BranchController::class, 'destroy'])
            ->middleware('access:suite.structure.delete')->name('structure.branches.destroy');
        Route::get('/structure', [StructureController::class, 'index'])
            ->middleware('access:suite.structure.view|suite.structure.create|suite.structure.update|suite.structure.delete')->name('structure.index');

        // Access: roles and members.
        Route::middleware('access:suite.access.create|suite.access.update')->group(function (): void {
            Route::get('/access/roles/create', [AccessRoleController::class, 'create'])->name('access.roles.create');
            Route::post('/access/roles', [AccessRoleController::class, 'store'])->name('access.roles.store');
            Route::get('/access/roles/{role}/edit', [AccessRoleController::class, 'edit'])->name('access.roles.edit');
            Route::put('/access/roles/{role}', [AccessRoleController::class, 'update'])->name('access.roles.update');
            Route::get('/access/members/create', [MemberController::class, 'create'])->name('access.members.create');
            Route::post('/access/members', [MemberController::class, 'store'])->name('access.members.store');
            Route::post('/access/members/invite', [MemberController::class, 'invite'])->name('access.members.invite');
        });
        Route::delete('/access/assignments/{assignment}', [MemberController::class, 'destroy'])
            ->middleware('access:suite.access.delete')->name('access.assignments.destroy');
        Route::middleware('access:suite.access.view|suite.access.create|suite.access.update|suite.access.delete')->group(function (): void {
            Route::get('/access/roles', [AccessRoleController::class, 'index'])->name('access.roles.index');
            Route::get('/access/members', [MemberController::class, 'index'])->name('access.members.index');
        });
    });
});
