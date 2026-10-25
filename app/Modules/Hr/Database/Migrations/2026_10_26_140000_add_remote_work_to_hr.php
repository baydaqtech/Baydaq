<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remote and hybrid work: each employee's pattern (on site, remote, or hybrid with set remote
     * weekdays), one-off requests to work from home, and punches made remotely.
     */
    public function up(): void
    {
        Schema::table('hr__employees', function (Blueprint $table) {
            $table->string('work_mode', 10)->default('onsite'); // onsite | remote | hybrid
            $table->json('remote_days')->nullable(); // weekdays 0 (Sunday) … 6 (Saturday) worked from home
        });

        Schema::table('hr__attendance_punches', function (Blueprint $table) {
            $table->boolean('is_remote')->default(false);
        });

        Schema::create('hr__remote_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->date('work_date');
            $table->string('reason')->nullable();
            $table->string('status', 20)->default('pending'); // pending | approved | rejected | cancelled
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
            $table->index(['client_id', 'status', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr__remote_requests');

        Schema::table('hr__attendance_punches', function (Blueprint $table) {
            $table->dropColumn('is_remote');
        });

        Schema::table('hr__employees', function (Blueprint $table) {
            $table->dropColumn(['work_mode', 'remote_days']);
        });
    }
};
