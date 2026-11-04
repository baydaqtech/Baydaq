<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Engagement: policies and circulars (with read and acknowledgement tracking), surveys (anonymous
     * or named) and recognition between colleagues (optionally with a bonus through payroll).
     * Audience columns: no company = the whole account; no branch = the whole company.
     */
    public function up(): void
    {
        Schema::create('hr__announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('kind', 10)->default('circular'); // policy | circular | news
            $table->string('title');
            $table->text('body');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->boolean('requires_ack')->default(false);
            $table->timestamp('published_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'published_at']);
        });

        Schema::create('hr__announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('announcement_id')->constrained('hr__announcements')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamp('acknowledged_at')->nullable();

            $table->unique(['announcement_id', 'employee_id']);
        });

        Schema::create('hr__surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('title');
            $table->string('description', 1000)->nullable();
            $table->json('questions'); // [{id, type: rating|nps|choice|text, label, options?}]
            $table->boolean('anonymous')->default(true);
            $table->string('status', 10)->default('open'); // open | closed
            $table->date('closes_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('hr__survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('survey_id')->constrained('hr__surveys')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('hr__employees')->nullOnDelete(); // empty when anonymous
            $table->char('respondent_key', 64); // one answer per person, without revealing who in anonymous surveys
            $table->json('answers');
            $table->timestamp('created_at');

            $table->unique(['survey_id', 'respondent_key']);
        });

        Schema::create('hr__recognitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete(); // who is recognised
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('given_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('giver_employee_id')->nullable()->constrained('hr__employees')->nullOnDelete();
            $table->string('badge', 20); // thanks | teamwork | customer | initiative | excellence
            $table->string('message', 500);
            $table->foreignId('bonus_id')->nullable()->constrained('hr__bonuses')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'created_at']);
            $table->index(['employee_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr__recognitions');
        Schema::dropIfExists('hr__survey_responses');
        Schema::dropIfExists('hr__surveys');
        Schema::dropIfExists('hr__announcement_reads');
        Schema::dropIfExists('hr__announcements');
    }
};
