<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hiring (openings with a public careers page, candidates through the stages, hire into an
     * employee with onboarding tasks) and performance reviews (cycles, self and manager scores).
     */
    public function up(): void
    {
        Schema::create('hr__job_openings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('job_id')->nullable()->constrained('hr__jobs')->nullOnDelete();
            $table->string('title_ar');
            $table->string('title_en');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('positions')->default(1);
            $table->string('status', 20)->default('open'); // draft | open | closed
            $table->date('closes_on')->nullable();
            $table->char('token', 32)->unique(); // the public careers link
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'status']);
        });

        Schema::create('hr__candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opening_id')->constrained('hr__job_openings')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('nationality', 2)->nullable();
            $table->string('cv_path')->nullable();
            $table->string('stage', 20)->default('applied'); // applied | screening | interview | offer | hired | rejected
            $table->unsignedTinyInteger('rating')->nullable(); // 1–5
            $table->text('notes')->nullable();
            $table->string('source', 20)->default('manual'); // careers | referral | manual
            $table->timestamp('interview_at')->nullable();
            $table->decimal('offer_salary', 12, 2)->nullable();
            $table->foreignId('employee_id')->nullable()->constrained('hr__employees')->nullOnDelete();
            $table->timestamp('consent_at')->nullable(); // PDPL: agreed to their data being kept for hiring
            $table->timestamps();

            $table->index(['opening_id', 'stage']);
        });

        Schema::create('hr__review_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id')->nullable(); // null = every company in reach at launch
            $table->string('name');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_on');
            $table->string('status', 20)->default('open'); // open | closed
            $table->json('criteria'); // [{key, label_ar, label_en, weight}]
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('hr__reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained('hr__review_cycles')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->json('self_scores')->nullable();
            $table->text('self_comment')->nullable();
            $table->timestamp('self_submitted_at')->nullable();
            $table->json('manager_scores')->nullable();
            $table->text('manager_comment')->nullable();
            $table->text('goals')->nullable(); // agreed for the next period
            $table->decimal('overall', 3, 2)->nullable(); // weighted 1–5
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['cycle_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr__reviews');
        Schema::dropIfExists('hr__review_cycles');
        Schema::dropIfExists('hr__candidates');
        Schema::dropIfExists('hr__job_openings');
    }
};
