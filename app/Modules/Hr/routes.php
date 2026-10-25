<?php

use App\Modules\Hr\Http\Controllers\AssistantController;
use App\Modules\Hr\Http\Controllers\AttendanceController;
use App\Modules\Hr\Http\Controllers\BreakController;
use App\Modules\Hr\Http\Controllers\CareersController;
use App\Modules\Hr\Http\Controllers\CatalogController;
use App\Modules\Hr\Http\Controllers\ChangeRequestController;
use App\Modules\Hr\Http\Controllers\ComplianceController;
use App\Modules\Hr\Http\Controllers\ContractController;
use App\Modules\Hr\Http\Controllers\EmployeeController;
use App\Modules\Hr\Http\Controllers\EmployeeDocumentController;
use App\Modules\Hr\Http\Controllers\ExpenseController;
use App\Modules\Hr\Http\Controllers\GovernmentController;
use App\Modules\Hr\Http\Controllers\HeadcountReportController;
use App\Modules\Hr\Http\Controllers\LeaveController;
use App\Modules\Hr\Http\Controllers\LetterController;
use App\Modules\Hr\Http\Controllers\MovementController;
use App\Modules\Hr\Http\Controllers\OrgChartController;
use App\Modules\Hr\Http\Controllers\OvertimeController;
use App\Modules\Hr\Http\Controllers\PayController;
use App\Modules\Hr\Http\Controllers\PayrollController;
use App\Modules\Hr\Http\Controllers\RecruitmentController;
use App\Modules\Hr\Http\Controllers\RemoteController;
use App\Modules\Hr\Http\Controllers\ReviewController;
use App\Modules\Hr\Http\Controllers\SelfServiceController;
use App\Modules\Hr\Http\Controllers\SeparationController;
use App\Modules\Hr\Http\Controllers\ShiftController;
use App\Modules\Hr\Http\Controllers\TaskController;
use App\Modules\Hr\Http\Controllers\ViolationController;
use Illuminate\Support\Facades\Route;

// The public check behind the QR code printed on a salary or experience letter (H12).
Route::middleware(['web', 'throttle:30,1'])->get('/verify/letters/{token}', [LetterController::class, 'verify'])->where('token', '[A-Za-z0-9]{40}')->name('hr.letters.verify');

// An opening's public careers page (hiring).
Route::middleware(['web', 'throttle:20,1'])->where(['token' => '[A-Za-z0-9]{32}'])->group(function (): void {
    Route::get('/careers/{token}', [CareersController::class, 'show'])->name('hr.careers.show');
    Route::post('/careers/{token}', [CareersController::class, 'apply'])->name('hr.careers.apply');
});

// An employee's own page (with or without a role in the suite).
Route::middleware(['web', 'auth', 'password.changed', 'api.tenant'])->prefix('me')->name('me.')->group(function (): void {
    Route::get('/', [SelfServiceController::class, 'show'])->name('home');
    Route::post('/leave', [SelfServiceController::class, 'requestLeave'])->middleware('throttle:10,1')->name('leave.store');
    Route::post('/advance', [SelfServiceController::class, 'requestAdvance'])->middleware('throttle:5,1')->middleware('feature:hr.advances')->name('advance.store');
    Route::post('/shifts/{shift}/claim', [SelfServiceController::class, 'claimShift'])->middleware('throttle:10,1')->middleware('feature:hr.shifts')->name('shifts.claim');
    Route::put('/pin', [SelfServiceController::class, 'setPin'])->middleware('throttle:5,1')->name('pin.update');
    Route::post('/tasks/{task}/status', [SelfServiceController::class, 'moveTask'])->middleware('throttle:60,1')->middleware('feature:hr.tasks')->name('tasks.move');
    Route::post('/breaks', [SelfServiceController::class, 'takeBreak'])->middleware('throttle:20,1')->middleware('feature:hr.breaks')->name('breaks.store');
    Route::post('/breaks/{break}/end', [SelfServiceController::class, 'endBreak'])->middleware('throttle:20,1')->middleware('feature:hr.breaks')->name('breaks.end');
    Route::post('/expenses', [SelfServiceController::class, 'claimExpense'])->middleware(['throttle:20,1', 'feature:hr.expenses'])->name('expenses.store');
    Route::post('/overtime', [SelfServiceController::class, 'requestOvertime'])->middleware(['throttle:10,1', 'feature:hr.overtime'])->name('overtime.store');
    Route::post('/overtime/{overtime}/cancel', [SelfServiceController::class, 'cancelOvertime'])->middleware(['throttle:10,1', 'feature:hr.overtime'])->name('overtime.cancel');
    Route::post('/remote', [SelfServiceController::class, 'requestRemote'])->middleware(['throttle:10,1', 'feature:hr.remote'])->name('remote.store');
    Route::post('/reviews/{review}', [SelfServiceController::class, 'selfReview'])->middleware(['throttle:10,1', 'feature:hr.performance'])->name('reviews.self');
    Route::post('/resignation', [SelfServiceController::class, 'resign'])->middleware(['throttle:5,1', 'feature:hr.offboarding'])->name('resignation.store');
    Route::post('/resignation/{separation}/withdraw', [SelfServiceController::class, 'withdrawResignation'])->middleware('throttle:5,1')->name('resignation.withdraw');
    Route::post('/violations/{violation}/response', [SelfServiceController::class, 'respondToViolation'])->middleware('throttle:10,1')->name('violations.respond');
});

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
        Route::put('/changes/{change}', [ChangeRequestController::class, 'decide'])->middleware('access:hr.employees.approve_changes|hr.employees.update')->name('changes.decide');
        Route::put('/settings', [ChangeRequestController::class, 'settings'])->middleware('access:hr.catalog.update')->name('settings.update');

        Route::middleware('access:hr.contracts.create|hr.contracts.update')->group(function (): void {
            Route::post('/employees/{employee}/contracts', [ContractController::class, 'store'])->name('contracts.store');
            Route::put('/employees/{employee}/contracts/{contract}', [ContractController::class, 'update'])->name('contracts.update');
        });

        Route::middleware('access:hr.documents.create|hr.documents.update')->group(function (): void {
            Route::post('/employees/{employee}/documents/scan', [EmployeeDocumentController::class, 'scan'])->middleware('throttle:20,1')->middleware('feature:suite.ai')->name('documents.scan');
            Route::post('/employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])->name('documents.store');
            Route::post('/employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'update'])->name('documents.update');
            Route::delete('/employees/{employee}/documents/{document}/files/{file}', [EmployeeDocumentController::class, 'deleteFile'])->name('documents.files.destroy');
        });
        Route::delete('/employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'destroy'])->middleware('access:hr.documents.delete')->name('documents.destroy');
        Route::post('/employees/{employee}/letters', [LetterController::class, 'store'])->middleware('access:hr.documents.create')->middleware('feature:hr.letters')->name('letters.store');
        Route::post('/employees/{employee}/letters/{letter}/revoke', [LetterController::class, 'revoke'])->middleware('access:hr.documents.update')->middleware('feature:hr.letters')->name('letters.revoke');
        Route::get('/employees/{employee}/letters/{letter}/pdf', [LetterController::class, 'pdf'])->middleware('access:hr.documents.view')->middleware('feature:hr.letters')->name('letters.pdf');
        Route::get('/documents/radar', [EmployeeDocumentController::class, 'radar'])->middleware('access:hr.documents.view')->name('documents.radar');
        Route::put('/renewals/{renewal}', [EmployeeDocumentController::class, 'renewal'])->middleware('access:hr.documents.update')->name('documents.renewals.update');
        Route::get('/employees/{employee}/documents/{document}/files/{file}', [EmployeeDocumentController::class, 'file'])->middleware('access:hr.documents.view')->name('documents.files.show');

        Route::post('/employees/{employee}/movements', [MovementController::class, 'store'])->middleware('access:hr.movements.create')->name('movements.store');
        Route::delete('/employees/{employee}/movements/{movement}', [MovementController::class, 'destroy'])->middleware('access:hr.movements.delete')->name('movements.destroy');
        Route::get('/movements', [MovementController::class, 'index'])->middleware('access:hr.movements.view|hr.movements.create')->name('movements.index');

        Route::middleware('access:hr.catalog.create|hr.catalog.update')->group(function (): void {
            Route::post('/catalog/{catalog}', [CatalogController::class, 'store'])->whereIn('catalog', ['sections', 'jobs', 'allowance-types', 'document-types', 'bonus-types', 'deduction-types', 'leave-types'])->name('catalog.store');
            Route::put('/catalog/{catalog}/{item}', [CatalogController::class, 'update'])->whereIn('catalog', ['sections', 'jobs', 'allowance-types', 'document-types', 'bonus-types', 'deduction-types', 'leave-types'])->name('catalog.update');
        });
        Route::get('/catalog', [CatalogController::class, 'index'])->middleware('access:hr.catalog.view|hr.catalog.create|hr.catalog.update')->name('catalog.index');

        Route::get('/attendance/punches/{punch}/selfie', [AttendanceController::class, 'selfie'])->middleware('access:hr.attendance.view')->name('attendance.selfie');
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
        Route::post('/employees/{employee}/leave-adjustments', [LeaveController::class, 'adjust'])->middleware('access:hr.leave.approve')->name('leave.adjust');
        Route::get('/leave', [LeaveController::class, 'index'])->middleware('access:hr.leave.view|hr.leave.approve')->name('leave.index');

        // Payroll.
        Route::post('/payroll', [PayrollController::class, 'build'])->middleware('access:hr.payroll.create')->name('payroll.build');
        Route::put('/payroll/{run}/approve', [PayrollController::class, 'approve'])->middleware('access:hr.payroll.approve')->name('payroll.approve');
        Route::put('/payroll/{run}/paid', [PayrollController::class, 'markPaid'])->middleware('access:hr.payroll.mark_paid')->name('payroll.paid');
        Route::get('/payroll/{run}/wps', [PayrollController::class, 'wps'])->middleware('access:hr.payroll.export')->name('payroll.wps');
        Route::get('/payroll/{run}', [PayrollController::class, 'show'])->middleware('access:hr.payroll.view')->name('payroll.show');
        Route::get('/payroll', [PayrollController::class, 'index'])->middleware('access:hr.payroll.view')->name('payroll.index');
        Route::get('/employees/{employee}/eosb', [PayrollController::class, 'eosb'])->middleware('access:hr.payroll.view_eosb')->name('payroll.eosb');
        Route::post('/employees/{employee}/final-settlement', [PayrollController::class, 'settle'])->middleware('access:hr.payroll.view_eosb')->name('payroll.settle');
        Route::get('/reports/eosb', [PayrollController::class, 'liability'])->middleware('access:hr.payroll.view_eosb')->name('reports.eosb');

        // Shifts, open shifts and holidays.
        Route::post('/shifts', [ShiftController::class, 'store'])->middleware('access:hr.shifts.create')->middleware('feature:hr.shifts')->name('shifts.store');
        Route::put('/shifts/{shift}', [ShiftController::class, 'update'])->middleware('access:hr.shifts.update')->middleware('feature:hr.shifts')->name('shifts.update');
        Route::delete('/shifts/{shift}', [ShiftController::class, 'destroy'])->middleware('access:hr.shifts.delete')->middleware('feature:hr.shifts')->name('shifts.destroy');
        Route::middleware('access:hr.shifts.update')->group(function (): void {
            Route::post('/holidays', [ShiftController::class, 'storeHoliday'])->middleware('feature:hr.shifts')->name('holidays.store');
            Route::post('/holidays/load', [ShiftController::class, 'loadHolidays'])->middleware('feature:hr.shifts')->name('holidays.load');
            Route::delete('/holidays/{holiday}', [ShiftController::class, 'destroyHoliday'])->middleware('feature:hr.shifts')->name('holidays.destroy');
        });
        Route::get('/shifts', [ShiftController::class, 'index'])->middleware('access:hr.shifts.view|hr.shifts.create')->middleware('feature:hr.shifts')->name('shifts.index');
        Route::post('/bonus-batch', [PayController::class, 'bonusBatch'])->middleware('access:hr.pay.create')->name('pay.bonus_batch');

        // Hiring and performance reviews.
        Route::middleware('feature:hr.recruitment')->group(function (): void {
            Route::post('/recruitment/openings', [RecruitmentController::class, 'storeOpening'])->middleware('access:hr.recruitment.create')->name('recruitment.openings.store');
            Route::put('/recruitment/openings/{opening}', [RecruitmentController::class, 'updateOpening'])->middleware('access:hr.recruitment.update')->name('recruitment.openings.update');
            Route::post('/recruitment/openings/{opening}/candidates', [RecruitmentController::class, 'storeCandidate'])->middleware('access:hr.recruitment.create')->name('recruitment.candidates.store');
            Route::put('/recruitment/candidates/{candidate}', [RecruitmentController::class, 'updateCandidate'])->middleware('access:hr.recruitment.update')->name('recruitment.candidates.update');
            Route::post('/recruitment/candidates/{candidate}/hire', [RecruitmentController::class, 'hire'])->middleware('access:hr.recruitment.approve')->name('recruitment.candidates.hire');
            Route::get('/recruitment/candidates/{candidate}/cv', [RecruitmentController::class, 'cv'])->middleware('access:hr.recruitment.view')->name('recruitment.candidates.cv');
            Route::get('/recruitment', [RecruitmentController::class, 'index'])->middleware('access:hr.recruitment.view')->name('recruitment.index');
        });
        Route::middleware('feature:hr.performance')->group(function (): void {
            Route::post('/reviews', [ReviewController::class, 'store'])->middleware('access:hr.reviews.create')->name('reviews.store');
            Route::put('/reviews/{review}', [ReviewController::class, 'update'])->middleware('access:hr.reviews.approve')->name('reviews.update');
            Route::put('/reviews/cycles/{cycle}/close', [ReviewController::class, 'close'])->middleware('access:hr.reviews.approve')->name('reviews.close');
            Route::get('/reviews', [ReviewController::class, 'index'])->middleware('access:hr.reviews.view|hr.reviews.approve')->name('reviews.index');
        });

        Route::middleware('feature:hr.expenses')->group(function (): void {
            Route::put('/expenses/{claim}/decision', [ExpenseController::class, 'decide'])->middleware('access:hr.expenses.approve')->name('expenses.decide');
            Route::put('/expenses/{claim}/paid', [ExpenseController::class, 'pay'])->middleware('access:hr.expenses.pay')->name('expenses.pay');
            Route::get('/expenses/{claim}/receipt', [ExpenseController::class, 'receipt'])->middleware('access:hr.expenses.view|hr.expenses.approve')->name('expenses.receipt');
            Route::get('/expenses', [ExpenseController::class, 'index'])->middleware('access:hr.expenses.view|hr.expenses.approve|hr.expenses.pay')->name('expenses.index');
        });
        Route::middleware('feature:hr.remote')->group(function (): void {
            Route::put('/employees/{employee}/work-pattern', [RemoteController::class, 'pattern'])->middleware('access:hr.remote.update')->name('remote.pattern');
            Route::put('/remote/{remoteRequest}', [RemoteController::class, 'decide'])->middleware('access:hr.remote.approve')->name('remote.decide');
            Route::get('/remote', [RemoteController::class, 'index'])->middleware('access:hr.remote.view|hr.remote.update|hr.remote.approve')->name('remote.index');
        });

        Route::middleware('feature:hr.overtime')->group(function (): void {
            Route::post('/overtime', [OvertimeController::class, 'store'])->middleware('access:hr.overtime.create')->name('overtime.store');
            Route::put('/overtime/{overtime}', [OvertimeController::class, 'decide'])->middleware('access:hr.overtime.approve')->name('overtime.decide');
            Route::get('/overtime', [OvertimeController::class, 'index'])->middleware('access:hr.overtime.view|hr.overtime.create|hr.overtime.approve')->name('overtime.index');
        });

        // Leaving and the disciplinary record.
        Route::middleware('feature:hr.offboarding')->group(function (): void {
            Route::post('/employees/{employee}/separations', [SeparationController::class, 'store'])->middleware('access:hr.offboarding.create')->name('separations.store');
            Route::put('/separations/{separation}', [SeparationController::class, 'update'])->middleware('access:hr.offboarding.approve')->name('separations.update');
            Route::get('/separations', [SeparationController::class, 'index'])->middleware('access:hr.offboarding.view|hr.offboarding.approve')->name('separations.index');
        });
        Route::middleware('feature:hr.violations')->group(function (): void {
            Route::post('/employees/{employee}/violations', [ViolationController::class, 'store'])->middleware('access:hr.violations.create')->name('violations.store');
            Route::put('/violations/{violation}', [ViolationController::class, 'update'])->middleware('access:hr.violations.approve')->name('violations.update');
            Route::get('/violations', [ViolationController::class, 'index'])->middleware('access:hr.violations.view|hr.violations.create|hr.violations.approve')->name('violations.index');
        });

        // Government trackers (Qiwa contracts, Muqeem visas, GOSI schemes).
        Route::middleware('feature:suite.gov')->group(function (): void {
            Route::get('/gov/qiwa', [GovernmentController::class, 'qiwa'])->middleware('access:hr.contracts.view')->name('gov.qiwa');
            Route::put('/gov/qiwa/{contract}', [GovernmentController::class, 'updateQiwa'])->middleware('access:hr.contracts.update')->name('gov.qiwa.update');
            Route::get('/gov/muqeem', [GovernmentController::class, 'muqeem'])->middleware('access:hr.documents.view')->name('gov.muqeem');
            Route::post('/employees/{employee}/visas', [GovernmentController::class, 'storeVisa'])->middleware('access:hr.documents.update')->name('gov.visas.store');
            Route::put('/gov/visas/{visa}', [GovernmentController::class, 'updateVisa'])->middleware('access:hr.documents.update')->name('gov.visas.update');
            Route::get('/gov/gosi', [GovernmentController::class, 'gosi'])->middleware('access:hr.employees.view_salaries')->name('gov.gosi');
        });
        Route::get('/org-chart', OrgChartController::class)->middleware('access:hr.employees.view')->name('org_chart');
        Route::get('/reports/nitaqat', [ComplianceController::class, 'nitaqat'])->middleware('access:hr.reports.view')->middleware('feature:hr.compliance')->name('reports.nitaqat');
        Route::put('/reports/nitaqat', [ComplianceController::class, 'saveTarget'])->middleware('access:hr.reports.view')->middleware('feature:hr.compliance')->name('reports.nitaqat.target');
        Route::match(['get', 'post'], '/reports/government', [ComplianceController::class, 'government'])->middleware('access:hr.employees.view_salaries')->middleware('feature:hr.compliance')->name('reports.government');
        Route::post('/assistant', [AssistantController::class, 'ask'])->middleware(['access:hr.employees.view|hr.documents.view|hr.leave.view|hr.attendance.view', 'throttle:20,1'])->middleware('feature:suite.ai')->name('assistant.ask');
        Route::get('/assistant', [AssistantController::class, 'show'])->middleware('access:hr.employees.view|hr.documents.view|hr.leave.view|hr.attendance.view')->middleware('feature:suite.ai')->name('assistant');
        Route::post('/tasks', [TaskController::class, 'store'])->middleware('access:hr.tasks.create')->middleware('feature:hr.tasks')->name('tasks.store');
        Route::put('/tasks/{task}', [TaskController::class, 'update'])->middleware('access:hr.tasks.update')->middleware('feature:hr.tasks')->name('tasks.update');
        Route::post('/tasks/{task}/status', [TaskController::class, 'move'])->middleware('access:hr.tasks.update')->middleware('feature:hr.tasks')->name('tasks.move');
        Route::post('/tasks/{task}/comments', [TaskController::class, 'comment'])->middleware('access:hr.tasks.view')->middleware('feature:hr.tasks')->name('tasks.comments');
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->middleware('access:hr.tasks.delete')->middleware('feature:hr.tasks')->name('tasks.destroy');
        Route::get('/tasks/{task}/photo', [TaskController::class, 'photo'])->middleware('access:hr.tasks.view')->middleware('feature:hr.tasks')->name('tasks.photo');
        Route::get('/tasks', [TaskController::class, 'index'])->middleware('access:hr.tasks.view|hr.tasks.create')->middleware('feature:hr.tasks')->name('tasks.index');
        Route::post('/breaks/settings', [BreakController::class, 'settings'])->middleware('access:hr.breaks.approve')->middleware('feature:hr.breaks')->name('breaks.settings');
        Route::post('/breaks/employees/{employee}', [BreakController::class, 'grant'])->middleware('access:hr.breaks.approve')->middleware('feature:hr.breaks')->name('breaks.grant');
        Route::post('/breaks/{break}/decide', [BreakController::class, 'decide'])->middleware('access:hr.breaks.approve')->middleware('feature:hr.breaks')->name('breaks.decide');
        Route::post('/breaks/{break}/end', [BreakController::class, 'end'])->middleware('access:hr.breaks.approve')->middleware('feature:hr.breaks')->name('breaks.end');
        Route::get('/breaks', [BreakController::class, 'index'])->middleware('access:hr.breaks.view|hr.breaks.approve')->middleware('feature:hr.breaks')->name('breaks.index');
        Route::get('/reports/headcount', HeadcountReportController::class)->middleware('access:hr.reports.view')->name('reports.headcount');

        Route::middleware('access:hr.employees.view|hr.employees.create|hr.employees.update')->group(function (): void {
            Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
            Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
        });
    });
