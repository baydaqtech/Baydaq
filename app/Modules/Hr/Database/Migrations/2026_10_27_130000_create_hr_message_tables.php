<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The message builder: templates with placeholders sent by hand, on a weekly or monthly day, or
     * when something happens to an employee (hired, new job, birthday, work anniversary, contract
     * ending). Every message sent is kept as the employee's inbox; the occurrence key stops a
     * template reaching the same person twice for the same event.
     */
    public function up(): void
    {
        Schema::create('hr__message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('name');
            $table->string('trigger', 10); // manual | weekly | monthly | event
            $table->string('event', 30)->nullable(); // hired | job_changed | birthday | work_anniversary | contract_ending
            $table->unsignedTinyInteger('weekday')->nullable(); // 0 = Sunday
            $table->unsignedTinyInteger('month_day')->nullable(); // 1–28
            $table->unsignedSmallInteger('days_before')->nullable(); // contract_ending: how many days ahead
            $table->json('channels'); // inapp, email, whatsapp
            $table->string('subject');
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->date('last_run_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'trigger', 'is_active']);
        });

        Schema::create('hr__messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('hr__message_templates')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->string('occurrence', 60);
            $table->string('subject');
            $table->text('body');
            $table->json('channels');
            $table->timestamp('sent_at');
            $table->timestamp('read_at')->nullable();

            $table->unique(['template_id', 'employee_id', 'occurrence'], 'hr_messages_once');
            $table->index(['employee_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr__messages');
        Schema::dropIfExists('hr__message_templates');
    }
};
