<?php

use App\Modules\Subscriptions\Http\Controllers\CardController;
use App\Modules\Subscriptions\Http\Controllers\CheckInController;
use App\Modules\Subscriptions\Http\Controllers\MemberController;
use App\Modules\Subscriptions\Http\Controllers\PlanController;
use Illuminate\Support\Facades\Route;

// A member's card (no login; the token in the link is the card).
Route::middleware(['web'])->get('/card/{token}', [CardController::class, 'show'])->where('token', '[A-Za-z0-9]{32}')->name('member.card');

Route::middleware(['web', 'auth', 'password.changed', 'suite.scope', 'module:subscriptions'])
    ->prefix('suite/{scope}/subscriptions')->where(['scope' => 'all|[oc]-[a-z0-9-]+'])->name('subscriptions.')
    ->group(function (): void {
        Route::middleware('access:subscriptions.plans.create|subscriptions.plans.update')->group(function (): void {
            Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
            Route::put('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
        });
        Route::get('/plans', [PlanController::class, 'index'])->middleware('access:subscriptions.plans.view|subscriptions.plans.create|subscriptions.plans.update')->name('plans.index');

        Route::post('/members', [MemberController::class, 'store'])->middleware('access:subscriptions.members.create')->name('members.store');
        Route::put('/members/{member}', [MemberController::class, 'update'])->middleware('access:subscriptions.members.update')->name('members.update');
        Route::post('/members/{member}/memberships', [MemberController::class, 'sell'])->middleware('access:subscriptions.members.sell')->name('members.sell');
        Route::post('/memberships/{membership}/freeze', [MemberController::class, 'freeze'])->middleware('access:subscriptions.members.freeze')->name('memberships.freeze');
        Route::post('/memberships/{membership}/cancel', [MemberController::class, 'cancel'])->middleware('access:subscriptions.members.cancel')->name('memberships.cancel');
        Route::get('/members', [MemberController::class, 'index'])->middleware('access:subscriptions.members.view')->name('members.index');
        Route::get('/members/{member}', [MemberController::class, 'show'])->middleware('access:subscriptions.members.view')->name('members.show');

        Route::post('/check-in', [CheckInController::class, 'store'])->middleware('access:subscriptions.check_ins.check_in')->name('check_in.store');
        Route::get('/check-in', [CheckInController::class, 'show'])->middleware('access:subscriptions.check_ins.view|subscriptions.check_ins.check_in')->name('check_in.show');
    });
