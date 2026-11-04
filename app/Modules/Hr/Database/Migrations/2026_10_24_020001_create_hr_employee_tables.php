<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Employees belong to a company (the employer) and a home branch. Placements are the
     * timeline of where they work (home and secondments); movements are the business events
     * that create placements.
     */
    public function up(): void
    {
        Schema::create('hr__employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('section_id')->nullable()->constrained('hr__sections')->nullOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('hr__jobs')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('hr__employees')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('employee_number', 30);
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('id_type', 20)->nullable(); // national_id | iqama | passport | gcc_id
            $table->text('national_id')->nullable();   // encrypted
            $table->char('national_id_hash', 64)->nullable(); // blind index for search and duplicates
            $table->char('nationality', 2)->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('birth_date')->nullable();
            $table->date('hired_on')->nullable();
            $table->date('terminated_on')->nullable();
            $table->string('status', 20)->default('draft');
            $table->text('iban')->nullable();          // encrypted
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'employee_number']);
            $table->unique(['client_id', 'national_id_hash']);
            $table->unique(['client_id', 'id']);
            $table->index(['client_id', 'company_id', 'status']);
            $table->index(['client_id', 'branch_id', 'status']);
            $table->foreign(['client_id', 'company_id'])->references(['client_id', 'id'])->on('platform__companies')->restrictOnDelete();
            $table->foreign(['company_id', 'branch_id'])->references(['company_id', 'id'])->on('platform__branches')->restrictOnDelete();
        });

        Schema::create('hr__employee_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->string('type', 20);   // transfer | secondment
            $table->string('status', 20); // scheduled | active | completed | cancelled
            $table->unsignedBigInteger('from_branch_id');
            $table->unsignedBigInteger('to_company_id');
            $table->unsignedBigInteger('to_branch_id');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'status', 'starts_on']);
            $table->index(['employee_id', 'starts_on']);
            $table->foreign('from_branch_id')->references('id')->on('platform__branches')->restrictOnDelete();
            $table->foreign(['to_company_id', 'to_branch_id'])->references(['company_id', 'id'])->on('platform__branches')->restrictOnDelete();
        });

        Schema::create('hr__employee_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->foreignId('movement_id')->nullable()->constrained('hr__employee_movements')->nullOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');
            $table->string('kind', 20); // home | secondment
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'starts_on']);
            $table->index(['branch_id', 'starts_on', 'ends_on']);
            $table->foreign(['company_id', 'branch_id'])->references(['company_id', 'id'])->on('platform__branches')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr__employee_placements');
        Schema::dropIfExists('hr__employee_movements');
        Schema::dropIfExists('hr__employees');
    }
};
