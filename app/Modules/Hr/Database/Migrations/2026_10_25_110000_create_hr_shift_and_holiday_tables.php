<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shifts per branch and day, assigned or open for anyone eligible to pick up (a pick-up at
     * another branch makes a one-day secondment), and the tenant's holidays and Ramadan period.
     */
    public function up(): void
    {
        Schema::create('hr__shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('employee_id')->nullable()->constrained('hr__employees')->nullOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('hr__jobs')->nullOnDelete(); // who may pick up an open shift
            $table->foreignId('movement_id')->nullable()->constrained('hr__employee_movements')->nullOnDelete();
            $table->date('shift_date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('minutes');
            $table->boolean('is_open')->default(false);
            $table->boolean('on_holiday')->default(false);
            $table->boolean('ramadan_hours')->default(false);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'shift_date']);
            $table->index(['employee_id', 'shift_date']);
            $table->index(['client_id', 'is_open', 'shift_date']);
            $table->foreign(['client_id', 'company_id'])->references(['client_id', 'id'])->on('platform__companies')->restrictOnDelete();
            $table->foreign(['company_id', 'branch_id'])->references(['company_id', 'id'])->on('platform__branches')->restrictOnDelete();
        });

        Schema::create('hr__holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('kind', 20); // public | ramadan
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();

            $table->index(['client_id', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr__holidays');
        Schema::dropIfExists('hr__shifts');
    }
};
