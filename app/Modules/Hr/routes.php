<?php

use App\Modules\Hr\Http\Controllers\AttendanceController;
use App\Modules\Hr\Http\Controllers\CatalogController;
use App\Modules\Hr\Http\Controllers\ContractController;
use App\Modules\Hr\Http\Controllers\EmployeeController;
use App\Modules\Hr\Http\Controllers\EmployeeDocumentController;
use App\Modules\Hr\Http\Controllers\HeadcountReportController;
use App\Modules\Hr\Http\Controllers\LeaveController;
use App\Modules\Hr\Http\Controllers\MovementController;
use App\Modules\Hr\Http\Controllers\OrgChartController;
use App\Modules\Hr\Http\Controllers\PayController;
use App\Modules\Hr\Http\Controllers\PayrollController;
use Illuminate\Support\Facades\Route;

/*
| HR pages live under /suite/{scope}/hr and need an active HR subscription. Change routes come
| before view routes; the controllers check each record against the user's reach.
*/
Route::middleware(['web', 'auth', 'password.changed', 'suite.scope', 'module:hr'])
    ->prefix('suite/{scope}/hr')->where(['scope' => 'all|[oc]-[a-z0-9-]+'])->name('hr.')
    ->group(function (): void {
        Route::middleware('access:hr.employees.create')->group(function (): void {
            Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
            Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        });
        Route::middleware('access:hr.employees.update')->group(function (): void {
            Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
            Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
            Route::put('/employees/{employee}/status', [EmployeeController::class, 'status'])->name('employees.status');
        });
        Route::post('/employees/{employee}/login', [EmployeeController::class, 'linkLogin'])->middleware('access:hr.employees.link_login')->name('employees.login');

        Route::middleware('access:hr.contracts.create|hr.contracts.update')->group(function (): void {
            Route::post('/employees/{employee}/contracts', [ContractController::class, 'store'])->name('contracts.store');
            Route::put('/employees/{employee}/contracts/{contract}', [ContractController::class, 'update'])->name('contracts.update');
        });

        Route::middleware('access:hr.documents.create|hr.documents.update')->group(function (): void {
            Route::post('/employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])->name('documents.store');
            Route::post('/employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'update'])->name('documents.update');
            Route::delete('/employees/{employee}/documents/{document}/files/{file}', [EmployeeDocumentController::class, 'deleteFile'])->name('documents.files.destroy');
        });
        Route::delete('/employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'destroy'])->middleware('access:hr.documents.delete')->name('documents.destroy');
        Route::get('/employees/{employee}/documents/{document}/files/{file}', [EmployeeDocumentController::class, 'file'])->middleware('access:hr.documents.view')->name('documents.files.show');

        Route::post('/employees/{employee}/movements', [MovementController::class, 'store'])->middleware('access:hr.movements.create')->name('movements.store');
        Route::delete('/employees/{employee}/movements/{movement}', [MovementController::class, 'destroy'])->middleware('access:hr.movements.delete')->name('movements.destroy');
        Route::get('/movements', [MovementController::class, 'index'])->middleware('access:hr.movements.view|hr.movements.create')->name('movements.index');

        Route::middleware('access:hr.catalog.create|hr.catalog.update')->group(function (): void {
            Route::post('/catalog/{catalog}', [CatalogController::class, 'store'])->whereIn('catalog', ['sections', 'jobs', 'allowance-types', 'document-types', 'bonus-types', 'deduction-types', 'leave-types'])->name('catalog.store');
            Route::put('/catalog/{catalog}/{item}', [CatalogController::class, 'update'])->whereIn('catalog', ['sections', 'jobs', 'allowance-types', 'document-types', 'bonus-types', 'deduction-types', 'leave-types'])->name('catalog.update');
        });
        Route::get('/catalog', [CatalogController::class, 'index'])->middleware('access:hr.catalog.view|hr.catalog.create|hr.catalog.update')->name('catalog.index');

        Route::get('/attendance', [AttendanceController::class, 'index'])->middleware('access:hr.attendance.view')->name('attendance.index');
        Route::put('/attendance/punches/{punch}/resolve', [AttendanceController::class, 'resolve'])->middleware('access:hr.attendance.resolve_flags')->name('attendance.resolve');
        Route::delete('/employees/{employee}/device', [AttendanceController::class, 'resetDevice'])->middleware('access:hr.attendance.reset_device')->name('attendance.device.reset');

        // Pay adjustments and advances, from the employee profile.
        Route::middleware('access:hr.pay.create')->group(function (): void {
            Route::post('/employees/{employee}/bonuses', [PayController::class, 'storeBonus'])->name('pay.bonuses.store');
            Route::post('/employees/{employee}/deductions', [PayController::class, 'storeDeduction'])->name('pay.deductions.store');
            Route::post('/employees/{employee}/advances', [PayController::class, 'storeAdvance'])->name('pay.advances.store');
        });
        Route::put('/employees/{employee}/pay/{kind}/{id}', [PayController::class, 'decide'])->whereIn('kind', ['bonuses', 'deductions'])->middleware('access:hr.pay.approve')->name('pay.decide');
        Route::put('/employees/{employee}/advances/{advance}', [PayController::class, 'advance'])->middleware('access:hr.pay.create|hr.pay.approve')->name('pay.advances.update');

        // Leave.
        Route::post('/employees/{employee}/leave', [LeaveController::class, 'store'])->middleware('access:hr.leave.create')->name('leave.store');
        Route::put('/leave/{leave}', [LeaveController::class, 'decide'])->middleware('access:hr.leave.approve')->name('leave.decide');
        Route::get('/leave', [LeaveController::class, 'index'])->middleware('access:hr.leave.view|hr.leave.approve')->name('leave.index');

        // Payroll.
        Route::post('/payroll', [PayrollController::class, 'build'])->middleware('access:hr.payroll.create')->name('payroll.build');
        Route::put('/payroll/{run}/approve', [PayrollController::class, 'approve'])->middleware('access:hr.payroll.approve')->name('payroll.approve');
        Route::put('/payroll/{run}/paid', [PayrollController::class, 'markPaid'])->middleware('access:hr.payroll.mark_paid')->name('payroll.paid');
        Route::get('/payroll/{run}/wps', [PayrollController::class, 'wps'])->middleware('access:hr.payroll.export')->name('payroll.wps');
        Route::get('/payroll/{run}', [PayrollController::class, 'show'])->middleware('access:hr.payroll.view')->name('payroll.show');
        Route::get('/payroll', [PayrollController::class, 'index'])->middleware('access:hr.payroll.view')->name('payroll.index');
        Route::get('/employees/{employee}/eosb', [PayrollController::class, 'eosb'])->middleware('access:hr.payroll.view_eosb')->name('payroll.eosb');

        Route::get('/org-chart', OrgChartController::class)->middleware('access:hr.employees.view')->name('org_chart');
        Route::get('/reports/headcount', HeadcountReportController::class)->middleware('access:hr.reports.view')->name('reports.headcount');

        Route::middleware('access:hr.employees.view|hr.employees.create|hr.employees.update')->group(function (): void {
            Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
            Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
        });
    });
