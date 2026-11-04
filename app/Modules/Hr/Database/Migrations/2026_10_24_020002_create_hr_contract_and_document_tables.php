<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr__contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->string('type', 20);   // fixed | open_ended
            $table->string('status', 20); // draft | active | ended
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('probation_days')->default(90);
            $table->decimal('basic_salary', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['client_id', 'ends_on']);
        });

        Schema::create('hr__contract_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('hr__contracts')->cascadeOnDelete();
            $table->foreignId('allowance_type_id')->constrained('hr__allowance_types')->restrictOnDelete();
            $table->string('calculation', 20); // fixed | percent_of_basic
            $table->decimal('value', 12, 2);   // the amount, or the percentage
            $table->decimal('amount', 12, 2);  // resolved monthly amount

            $table->unique(['contract_id', 'allowance_type_id']);
        });

        Schema::create('hr__employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained('hr__document_types')->restrictOnDelete();
            $table->text('number')->nullable(); // encrypted
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->json('data')->nullable();
            // The next threshold (in days before expiry) that has not been alerted yet.
            $table->date('next_alert_on')->nullable();
            $table->json('alerted')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'document_type_id']);
            $table->index(['client_id', 'next_alert_on']);
            $table->index(['client_id', 'expires_on']);
        });

        Schema::create('hr__employee_document_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('hr__employee_documents')->cascadeOnDelete();
            $table->string('disk', 30);
            $table->string('path');
            $table->string('name');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr__employee_document_files');
        Schema::dropIfExists('hr__employee_documents');
        Schema::dropIfExists('hr__contract_allowances');
        Schema::dropIfExists('hr__contracts');
    }
};
