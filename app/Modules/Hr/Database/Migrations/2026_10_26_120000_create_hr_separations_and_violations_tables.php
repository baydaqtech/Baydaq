<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Leaving (resignation, termination, contract end…) with its exit checklist, and the
     * disciplinary record: violations, the employee's answer, and the penalty applied.
     */
    public function up(): void
    {
        Schema::create('hr__separations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->string('kind', 20); // resignation | termination | contract_end | retirement | mutual
            $table->string('status', 20); // submitted | deferred | accepted | withdrawn | completed | cancelled
            $table->string('source', 10)->default('manager'); // employee | manager
            $table->date('submitted_on');
            $table->date('last_day');
            $table->date('deemed_accept_on')->nullable(); // a resignation unanswered by then counts as accepted
            $table->boolean('deemed')->default(false);
            $table->text('reason')->nullable();
            $table->json('checklist')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['employee_id', 'status']);
        });

        Schema::create('hr__violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->string('category', 30); // late | absence | early_leave | break_overdue | location | conduct | safety | other
            $table->date('occurred_on');
            $table->unsignedSmallInteger('minutes')->nullable(); // how late, how early, how long over
            $table->unsignedTinyInteger('occurrence')->default(1); // nth of this kind within 180 days
            $table->text('description')->nullable();
            $table->string('penalty', 20)->default('none'); // none | warning | written_warning | deduction | suspension | dismissal
            $table->decimal('deduction_days', 4, 2)->nullable();
            $table->foreignId('deduction_id')->nullable()->constrained('hr__deductions')->nullOnDelete();
            $table->string('status', 20)->default('open'); // open | notified | closed | cancelled
            $table->text('employee_response')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('source', 20)->default('manual'); // manual | attendance
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'occurred_on']);
            $table->index(['employee_id', 'category', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr__violations');
        Schema::dropIfExists('hr__separations');
    }
};
