<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr__leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            // Days per year accrued (0 for types without a balance, e.g. unpaid or sick).
            $table->decimal('days_per_year', 5, 2)->default(0);
            $table->boolean('is_paid')->default(true);
            $table->boolean('needs_balance')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('hr__leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('hr__leave_types')->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('days', 5, 2);
            $table->string('status', 20)->default('pending'); // pending | approved | rejected | cancelled
            $table->string('reason')->nullable();
            $table->string('decision_note')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'starts_on']);
            $table->index(['client_id', 'status']);
        });

        Schema::create('hr__payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->date('pay_month');
            $table->string('status', 20)->default('draft'); // draft | approved | paid
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->decimal('total_employer_gosi', 14, 2)->default(0);
            $table->unsignedInteger('employees_count')->default(0);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->date('paid_on')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'pay_month']);
            $table->foreign(['client_id', 'company_id'])->references(['client_id', 'id'])->on('platform__companies')->restrictOnDelete();
        });

        Schema::create('hr__payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('hr__payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->restrictOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->decimal('basic', 12, 2);
            $table->decimal('allowances', 12, 2);
            $table->decimal('bonuses', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('advance_installments', 12, 2)->default(0);
            $table->decimal('unpaid_leave', 12, 2)->default(0);
            $table->decimal('gosi_employee', 12, 2)->default(0);
            $table->decimal('gosi_employer', 12, 2)->default(0);
            $table->decimal('gross', 12, 2);
            $table->decimal('net', 12, 2);
            $table->unsignedTinyInteger('working_days')->default(30);
            $table->decimal('unpaid_leave_days', 5, 2)->default(0);
            // Line items for the payslip: [{kind, label_ar, label_en, amount}].
            $table->json('breakdown')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr__payroll_lines');
        Schema::dropIfExists('hr__payroll_runs');
        Schema::dropIfExists('hr__leave_requests');
        Schema::dropIfExists('hr__leave_types');
    }
};
