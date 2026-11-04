<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pay adjustments outside the contract: bonuses, deductions (within the legal caps), and
     * salary advances with their repayment schedule. All of them end up on a payroll line.
     */
    public function up(): void
    {
        foreach (['hr__bonus_types', 'hr__deduction_types'] as $table) {
            Schema::create($table, function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained()->cascadeOnDelete();
                $table->string('name_ar');
                $table->string('name_en');
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        Schema::create('hr__bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->foreignId('bonus_type_id')->constrained('hr__bonus_types')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending'); // pending | approved | paid | rejected
            $table->date('pay_month');                        // first day of the month it belongs to
            $table->string('reason')->nullable();
            $table->foreignId('payroll_line_id')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'pay_month', 'status']);
        });

        Schema::create('hr__deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->foreignId('deduction_type_id')->constrained('hr__deduction_types')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending');
            $table->date('pay_month');
            $table->string('reason')->nullable();
            // Where it came from: manual | attendance | violation | module:{name}
            $table->string('origin', 40)->default('manual');
            $table->foreignId('payroll_line_id')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'pay_month', 'status']);
        });

        Schema::create('hr__advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('installments');
            $table->date('first_installment_month');
            $table->string('status', 20)->default('pending'); // pending | approved | disbursed | settled | rejected
            $table->string('disbursement', 20)->nullable();   // cash | transfer | payroll
            $table->date('disbursed_on')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        Schema::create('hr__advance_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advance_id')->constrained('hr__advances')->cascadeOnDelete();
            $table->date('due_month');
            $table->decimal('amount', 12, 2);
            $table->decimal('paid', 12, 2)->default(0);
            $table->string('status', 20)->default('due'); // due | paid | skipped
            $table->foreignId('payroll_line_id')->nullable();

            $table->unique(['advance_id', 'due_month']);
        });

        Schema::create('hr__advance_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advance_id')->constrained('hr__advances')->cascadeOnDelete();
            $table->foreignId('installment_id')->nullable()->constrained('hr__advance_installments')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 20); // payroll | cash | transfer | settlement
            $table->date('paid_on');
            $table->string('reference')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr__advance_repayments');
        Schema::dropIfExists('hr__advance_installments');
        Schema::dropIfExists('hr__advances');
        Schema::dropIfExists('hr__deductions');
        Schema::dropIfExists('hr__bonuses');
        Schema::dropIfExists('hr__deduction_types');
        Schema::dropIfExists('hr__bonus_types');
    }
};
