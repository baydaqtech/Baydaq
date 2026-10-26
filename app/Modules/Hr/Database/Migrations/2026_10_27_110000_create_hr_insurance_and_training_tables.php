<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Medical insurance (policies per company, insured employees and dependants with class and card)
     * and training (sessions, who attends, certificates with expiry).
     */
    public function up(): void
    {
        Schema::create('hr__insurance_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->string('insurer');
            $table->string('policy_number', 60)->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'company_id']);
        });

        Schema::create('hr__insurance_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('policy_id')->constrained('hr__insurance_policies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->string('relation', 10)->default('self'); // self | spouse | child | parent
            $table->string('name')->nullable();              // the dependant's name
            $table->string('class', 10);                     // VIP, A, B, C...
            $table->string('card_number', 60)->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->decimal('premium', 10, 2)->nullable();
            $table->string('status', 10)->default('active'); // active | removed
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        Schema::create('hr__trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->string('title');
            $table->string('provider')->nullable();
            $table->string('location')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->decimal('hours', 6, 2)->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->unsignedSmallInteger('certificate_months')->nullable(); // how long the certificate is valid
            $table->string('status', 10)->default('planned'); // planned | done | cancelled
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'starts_on']);
        });

        Schema::create('hr__training_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_id')->constrained('hr__trainings')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->string('status', 10)->default('enrolled'); // enrolled | attended | missed
            $table->string('certificate_number', 60)->nullable();
            $table->date('certificate_expires_on')->nullable();
            $table->timestamps();

            $table->unique(['training_id', 'employee_id']);
            $table->index(['employee_id', 'certificate_expires_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr__training_attendees');
        Schema::dropIfExists('hr__trainings');
        Schema::dropIfExists('hr__insurance_members');
        Schema::dropIfExists('hr__insurance_policies');
    }
};
