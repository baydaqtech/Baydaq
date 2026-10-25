<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tasks given to employees and followed up from the app, and work breaks: a scheduled break
     * on the shift, breaks granted by a manager or asked for by the employee.
     */
    public function up(): void
    {
        Schema::create('hr__tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id'); // the assignee's branch, or where the task happens
            $table->foreignId('assignee_id')->nullable()->constrained('hr__employees')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 10)->default('normal'); // low | normal | high | urgent
            $table->string('status', 15)->default('todo'); // todo | in_progress | done | cancelled
            $table->json('checklist')->nullable(); // [{text, done}]
            $table->boolean('needs_photo')->default(false);
            $table->string('photo_path')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['assignee_id', 'status']);
        });

        Schema::create('hr__task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('hr__tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('hr__breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->string('kind', 12); // scheduled | granted | requested
            $table->string('status', 12); // requested | on_break | ended | rejected | cancelled
            $table->unsignedSmallInteger('minutes');
            $table->date('work_date');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['employee_id', 'work_date']);
        });

        Schema::table('hr__shifts', function (Blueprint $table) {
            $table->time('break_at')->nullable()->after('ends_at');
            $table->unsignedSmallInteger('break_minutes')->nullable()->after('break_at');
        });
    }

    public function down(): void
    {
        Schema::table('hr__shifts', function (Blueprint $table) {
            $table->dropColumn(['break_at', 'break_minutes']);
        });
        Schema::dropIfExists('hr__breaks');
        Schema::dropIfExists('hr__task_comments');
        Schema::dropIfExists('hr__tasks');
    }
};
