<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A client's own look for the suite, /me and the employee app (when its package has custom
     * branding): colours, logo, name, corner roundness and the default light or dark mode.
     */
    public function up(): void
    {
        Schema::create('platform__themes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('app_name', 60)->nullable();
            $table->string('logo_path')->nullable();
            $table->char('primary_color', 7)->nullable();
            $table->char('accent_color', 7)->nullable();
            $table->string('radius', 10)->default('medium'); // small | medium | large
            $table->string('mode', 10)->default('light');    // light | dark | system
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform__themes');
    }
};
