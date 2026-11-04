<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sections and jobs are shared by the whole owner (client). Names are unique per language
     * on a normalized key (case, spacing and Arabic letter variants folded). A job can sit under
     * any section; a job or section can be blocked for particular companies.
     */
    public function up(): void
    {
        foreach (['hr__sections', 'hr__jobs'] as $table) {
            Schema::create($table, function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained()->cascadeOnDelete();
                $table->string('name_ar');
                $table->string('name_en');
                $table->string('name_ar_key');
                $table->string('name_en_key');
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['client_id', 'name_ar_key']);
                $table->unique(['client_id', 'name_en_key']);
                $table->unique(['client_id', 'id']);
            });
        }

        Schema::create('hr__job_section', function (Blueprint $table) {
            $table->foreignId('job_id')->constrained('hr__jobs')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('hr__sections')->cascadeOnDelete();

            $table->primary(['job_id', 'section_id']);
        });

        Schema::create('hr__job_company_blocks', function (Blueprint $table) {
            $table->foreignId('job_id')->constrained('hr__jobs')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('platform__companies')->cascadeOnDelete();

            $table->primary(['job_id', 'company_id']);
        });

        Schema::create('hr__section_company_blocks', function (Blueprint $table) {
            $table->foreignId('section_id')->constrained('hr__sections')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('platform__companies')->cascadeOnDelete();

            $table->primary(['section_id', 'company_id']);
        });

        Schema::create('hr__allowance_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('calculation', 20); // fixed | percent_of_basic
            $table->decimal('default_value', 12, 2)->default(0);
            $table->boolean('counts_for_gosi')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['client_id', 'id']);
        });

        Schema::create('hr__document_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            $table->boolean('has_number')->default(true);
            $table->boolean('has_expiry')->default(true);
            // Days before expiry at which to alert, e.g. [90, 30, 7].
            $table->json('alert_days')->nullable();
            // Tenant-defined extra fields: [{key, label_ar, label_en, type, required, options?}].
            $table->json('fields')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['client_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr__document_types');
        Schema::dropIfExists('hr__allowance_types');
        Schema::dropIfExists('hr__section_company_blocks');
        Schema::dropIfExists('hr__job_company_blocks');
        Schema::dropIfExists('hr__job_section');
        Schema::dropIfExists('hr__jobs');
        Schema::dropIfExists('hr__sections');
    }
};
