<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The tenant tree: organizations → companies → branches, all owned by a client.
     * Composite foreign keys keep a child inside its parent's tenant at the database level.
     */
    public function up(): void
    {
        Schema::create('platform__organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 60);
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['client_id', 'slug']);
            $table->unique(['client_id', 'id']);
        });

        Schema::create('platform__companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('organization_id');
            $table->string('slug', 60);
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('cr_number', 20)->nullable();
            $table->string('vat_number', 15)->nullable();
            $table->string('unified_number', 20)->nullable();
            $table->string('gosi_number', 30)->nullable();
            $table->string('qiwa_id', 30)->nullable();
            $table->string('timezone', 64)->default('Asia/Riyadh');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['client_id', 'slug']);
            $table->unique(['client_id', 'id']);
            $table->foreign(['client_id', 'organization_id'])
                ->references(['client_id', 'id'])->on('platform__organizations')->cascadeOnDelete();
        });

        Schema::create('platform__branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->string('code', 20);
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('city_ar')->nullable();
            $table->string('city_en')->nullable();
            $table->boolean('is_head_office')->default(false);
            // MySQL has no partial unique index: this generated column is the company id only for
            // the head office, so a unique index on it allows exactly one head office per company.
            $table->unsignedBigInteger('head_office_company_id')->nullable()
                ->storedAs('IF(`is_head_office`, `company_id`, NULL)')->unique();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['client_id', 'id']);
            $table->unique(['company_id', 'id']);
            $table->unique(['company_id', 'code']);
            // MySQL forbids CASCADE on a key whose column feeds a stored generated column, so a
            // company is deleted through its action, which removes the branches first.
            $table->foreign(['client_id', 'company_id'])
                ->references(['client_id', 'id'])->on('platform__companies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform__branches');
        Schema::dropIfExists('platform__companies');
        Schema::dropIfExists('platform__organizations');
    }
};
