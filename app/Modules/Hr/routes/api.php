<?php

use App\Modules\Hr\Http\Controllers\Api\AuthController;
use App\Modules\Hr\Http\Controllers\Api\LeaveController;
use App\Modules\Hr\Http\Controllers\Api\LocationController;
use App\Modules\Hr\Http\Controllers\Api\MeController;
use App\Modules\Hr\Http\Controllers\Api\PunchController;
use Illuminate\Support\Facades\Route;

/*
| Employee app. Sign in with email + password + device id; the token is tied to that device.
*/
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('auth.login');

Route::middleware(['auth:sanctum', 'api.tenant'])->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/me', MeController::class)->name('me');
    Route::get('/leave', [LeaveController::class, 'index'])->name('leave.index');
    Route::post('/leave', [LeaveController::class, 'store'])->middleware('throttle:10,1')->name('leave.store');
    Route::get('/attendance/locations', [LocationController::class, 'index'])->name('attendance.locations');
    Route::get('/attendance/punches', [PunchController::class, 'index'])->name('attendance.punches.index');
    Route::post('/attendance/punches', [PunchController::class, 'store'])->middleware('throttle:30,1')->name('attendance.punches.store');
});
