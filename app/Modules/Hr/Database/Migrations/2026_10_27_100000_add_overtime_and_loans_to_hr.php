<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Loans are advances of another kind (same installments and payroll recovery, other limits).
     * Overtime hours are requested or recorded, approved, and paid through payroll as a bonus of the
     * system "Overtime" type. Bonus types get a system key so the system can find its own types.
     */
    public function up(): void
    {
        Schema::table('hr__advances', function (Blueprint $table) {
            $table->string('kind', 10)->default('advance')->after('employee_id'); // advance | loan
        });

        Schema::table('hr__bonus_types', function (Blueprint $table) {
            $table->string('system_key', 30)->nullable();
            $table->unique(['client_id', 'system_key']);
        });

        Schema::create('hr__overtime', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');
            $table->date('work_date');
            $table->decimal('hours', 5, 2);
            $table->string('reason')->nullable();
            $table->string('source', 10)->default('employee'); // employee | manager
            $table->string('status', 20)->default('pending'); // pending | approved | rejected | cancelled
            $table->decimal('amount', 12, 2)->nullable(); // worked out on approval (Labor Law Art. 107)
            $table->foreignId('bonus_id')->nullable()->constrained('hr__bonuses')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['employee_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr__overtime');

        Schema::table('hr__bonus_types', function (Blueprint $table) {
            $table->dropUnique(['client_id', 'system_key']);
            $table->dropColumn('system_key');
        });

        Schema::table('hr__advances', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
