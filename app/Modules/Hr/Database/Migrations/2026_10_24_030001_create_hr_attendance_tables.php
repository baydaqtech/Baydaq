<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attendance: one bound device per employee, and punches with where they happened and how
     * they were judged. A punch at a branch that is not the employee's home (and not covered by
     * a placement that day) is accepted but flagged.
     */
    public function up(): void
    {
        Schema::create('hr__attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->unique()->constrained('hr__employees')->cascadeOnDelete();
            $table->string('device_id', 120);
            $table->string('platform', 20)->nullable();
            $table->string('model')->nullable();
            $table->timestamp('bound_at');
            $table->timestamps();

            $table->unique(['client_id', 'device_id']);
        });

        Schema::create('hr__attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->string('type', 10); // in | out
            $table->timestamp('punched_at');
            $table->date('work_date'); // in the company's timezone
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('accuracy_m')->nullable();
            $table->boolean('mocked')->default(false);
            $table->unsignedBigInteger('work_location_id')->nullable();
            // The branch the punch was matched to, and the employee's home at that moment.
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('home_branch_id');
            $table->string('flag', 30)->nullable(); // outside_home_branch | other_company | mocked_location
            $table->string('resolution', 20)->nullable(); // accepted | rejected
            $table->string('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('source', 20)->default('mobile'); // mobile | web | manual
            $table->string('device_id', 120)->nullable();
            $table->timestamps();

            $table->index(['client_id', 'work_date', 'branch_id']);
            $table->index(['employee_id', 'punched_at']);
            $table->index(['client_id', 'flag', 'resolution']);
            $table->foreign('work_location_id')->references('id')->on('platform__work_locations')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('platform__branches')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr__attendance_punches');
        Schema::dropIfExists('hr__attendance_devices');
    }
};
