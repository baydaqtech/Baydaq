<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Brands: a company's trade names. A branch can carry several (a cloud kitchen cooking for
     * three brands); a product or menu belongs to one brand or to the company as a whole.
     */
    public function up(): void
    {
        Schema::create('platform__brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->string('slug', 60);
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('logo')->nullable();
            $table->string('color', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
            $table->unique(['client_id', 'id']);
            $table->foreign(['client_id', 'company_id'])->references(['client_id', 'id'])->on('platform__companies')->cascadeOnDelete();
        });

        Schema::create('platform__brand_branch', function (Blueprint $table) {
            $table->foreignId('brand_id')->constrained('platform__brands')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('platform__branches')->cascadeOnDelete();

            $table->primary(['brand_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform__brand_branch');
        Schema::dropIfExists('platform__brands');
    }
};
