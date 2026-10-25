<?php

use App\Modules\Hr\Http\Controllers\Api\AdvanceController;
use App\Modules\Hr\Http\Controllers\Api\AuthController;
use App\Modules\Hr\Http\Controllers\Api\BreakController;
use App\Modules\Hr\Http\Controllers\Api\LeaveController;
use App\Modules\Hr\Http\Controllers\Api\LocationController;
use App\Modules\Hr\Http\Controllers\Api\MeController;
use App\Modules\Hr\Http\Controllers\Api\PinController;
use App\Modules\Hr\Http\Controllers\Api\PunchController;
use App\Modules\Hr\Http\Controllers\Api\RecordController;
use App\Modules\Hr\Http\Controllers\Api\ShiftController;
use App\Modules\Hr\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

/*
| Employee app. Sign in with email + password + device id; the token is tied to that device.
*/
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('auth.login');

Route::middleware(['auth:sanctum', 'api.tenant'])->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/me', MeController::class)->name('me');
    Route::get('/leave', [LeaveController::class, 'index'])->middleware('feature:mobile.leave')->name('leave.index');
    Route::post('/leave', [LeaveController::class, 'store'])->middleware('throttle:10,1')->middleware('feature:mobile.leave')->name('leave.store');
    Route::get('/advances', [AdvanceController::class, 'index'])->middleware(['feature:mobile.advances', 'feature:hr.advances'])->name('advances.index');
    Route::post('/advances/preview', [AdvanceController::class, 'preview'])->middleware('throttle:30,1')->middleware(['feature:mobile.advances', 'feature:hr.advances'])->name('advances.preview');
    Route::post('/advances', [AdvanceController::class, 'store'])->middleware('throttle:5,1')->middleware(['feature:mobile.advances', 'feature:hr.advances'])->name('advances.store');
    Route::put('/me/pin', PinController::class)->middleware('throttle:5,1')->name('me.pin');
    Route::get('/tasks', [TaskController::class, 'index'])->middleware(['feature:mobile.tasks', 'feature:hr.tasks'])->name('tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->middleware('throttle:30,1')->middleware(['feature:mobile.tasks', 'feature:hr.tasks'])->name('tasks.store');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->middleware(['feature:mobile.tasks', 'feature:hr.tasks'])->name('tasks.show');
    Route::post('/tasks/{task}/status', [TaskController::class, 'move'])->middleware('throttle:60,1')->middleware(['feature:mobile.tasks', 'feature:hr.tasks'])->name('tasks.move');
    Route::post('/tasks/{task}/comments', [TaskController::class, 'comment'])->middleware('throttle:60,1')->middleware(['feature:mobile.tasks', 'feature:hr.tasks'])->name('tasks.comments');
    Route::get('/breaks', [BreakController::class, 'show'])->middleware(['feature:mobile.breaks', 'feature:hr.breaks'])->name('breaks.show');
    Route::post('/breaks', [BreakController::class, 'store'])->middleware('throttle:20,1')->middleware(['feature:mobile.breaks', 'feature:hr.breaks'])->name('breaks.store');
    Route::post('/breaks/{break}/end', [BreakController::class, 'end'])->middleware('throttle:20,1')->middleware(['feature:mobile.breaks', 'feature:hr.breaks'])->name('breaks.end');
    Route::get('/shifts', [ShiftController::class, 'index'])->middleware(['feature:mobile.shifts', 'feature:hr.shifts'])->name('shifts.index');
    Route::post('/shifts/{shift}/claim', [ShiftController::class, 'claim'])->middleware('throttle:10,1')->middleware(['feature:mobile.shifts', 'feature:hr.shifts'])->name('shifts.claim');
    Route::get('/expenses', [RecordController::class, 'expenses'])->middleware('feature:mobile.expenses')->name('expenses.index');
    Route::post('/expenses', [RecordController::class, 'claimExpense'])->middleware(['throttle:20,1', 'feature:mobile.expenses', 'feature:hr.expenses'])->name('expenses.store');
    Route::get('/overtime', [RecordController::class, 'overtime'])->middleware(['feature:mobile.overtime', 'feature:hr.overtime'])->name('overtime.index');
    Route::post('/overtime', [RecordController::class, 'requestOvertime'])->middleware(['throttle:10,1', 'feature:mobile.overtime', 'feature:hr.overtime'])->name('overtime.store');
    Route::post('/overtime/{overtime}/cancel', [RecordController::class, 'cancelOvertime'])->middleware(['throttle:10,1', 'feature:mobile.overtime', 'feature:hr.overtime'])->name('overtime.cancel');
    Route::get('/remote', [RecordController::class, 'remote'])->middleware('feature:mobile.remote')->name('remote.show');
    Route::post('/remote/requests', [RecordController::class, 'requestRemote'])->middleware(['throttle:10,1', 'feature:mobile.remote', 'feature:hr.remote'])->name('remote.requests.store');
    Route::get('/reviews', [RecordController::class, 'reviews'])->middleware(['feature:mobile.reviews', 'feature:hr.performance'])->name('reviews.index');
    Route::post('/reviews/{review}', [RecordController::class, 'selfReview'])->middleware(['throttle:10,1', 'feature:mobile.reviews', 'feature:hr.performance'])->name('reviews.self');
    Route::get('/record', [RecordController::class, 'show'])->middleware('feature:mobile.resignation')->name('record.show');
    Route::post('/resignation', [RecordController::class, 'resign'])->middleware(['throttle:5,1', 'feature:mobile.resignation', 'feature:hr.offboarding'])->name('resignation.store');
    Route::post('/resignation/{separation}/withdraw', [RecordController::class, 'withdraw'])->middleware(['throttle:5,1', 'feature:mobile.resignation'])->name('resignation.withdraw');
    Route::post('/violations/{violation}/response', [RecordController::class, 'respond'])->middleware(['throttle:10,1', 'feature:hr.violations'])->name('violations.respond');
    Route::get('/attendance/locations', [LocationController::class, 'index'])->middleware('feature:mobile.attendance')->name('attendance.locations');
    Route::get('/attendance/punches', [PunchController::class, 'index'])->middleware('feature:mobile.attendance')->name('attendance.punches.index');
    Route::post('/attendance/punches', [PunchController::class, 'store'])->middleware('throttle:30,1')->middleware('feature:mobile.attendance')->name('attendance.punches.store');
});
