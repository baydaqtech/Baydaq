<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Leave balance rules per leave type: the yearly days, the higher rate after some years of
     * service (Labor Law art. 109: 21 days, 30 after five years), how the days build up (all at
     * once, monthly or daily), whether the leave year follows the calendar or the hire date, and
     * how much may roll into the next year. Plus manual adjustments (opening balances, corrections).
     */
    public function up(): void
    {
        Schema::table('hr__leave_types', function (Blueprint $table) {
            $table->decimal('senior_days_per_year', 5, 2)->nullable()->after('days_per_year');
            $table->unsignedTinyInteger('senior_after_years')->nullable()->after('senior_days_per_year');
            $table->string('accrual', 10)->default('monthly')->after('senior_after_years'); // yearly | monthly | daily
            $table->string('year_basis', 10)->default('calendar')->after('accrual');        // calendar | hire_date
            $table->decimal('carry_over_days', 5, 2)->nullable()->after('year_basis');      // null = carry everything
            $table->boolean('allow_negative')->default(false)->after('carry_over_days');
        });

        DB::table('hr__leave_types')->where('needs_balance', true)->where('days_per_year', 21)->update(['senior_days_per_year' => 30, 'senior_after_years' => 5]);

        Schema::create('hr__leave_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('hr__leave_types')->cascadeOnDelete();
            $table->decimal('days', 6, 2);                 // + adds to the balance, − takes from it
            $table->date('effective_on');
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'leave_type_id', 'effective_on'], 'hr__leave_adj_employee_type_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr__leave_adjustments');
        Schema::table('hr__leave_types', function (Blueprint $table) {
            $table->dropColumn(['senior_days_per_year', 'senior_after_years', 'accrual', 'year_basis', 'carry_over_days', 'allow_negative']);
        });
    }
};
