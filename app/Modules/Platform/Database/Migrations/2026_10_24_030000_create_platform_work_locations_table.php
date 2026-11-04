<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Geofences for attendance. A location serves one or more branches (a shared building can
     * host several branches or companies), so the link is many-to-many.
     */
    public function up(): void
    {
        Schema::create('platform__work_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('radius_m')->default(100);
            // Optional Wi-Fi identifiers (BSSIDs) that also count as "inside", for indoor branches.
            $table->json('wifi_bssids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['client_id', 'id']);
        });

        Schema::create('platform__work_location_branch', function (Blueprint $table) {
            $table->foreignId('work_location_id')->constrained('platform__work_locations')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('platform__branches')->cascadeOnDelete();

            $table->primary(['work_location_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform__work_location_branch');
        Schema::dropIfExists('platform__work_locations');
    }
};
