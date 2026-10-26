<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('type', 30);
            $table->string('relationship', 20)->nullable();
            $table->decimal('partnership_share', 5, 2)->nullable();
            $table->string('status', 20)->default('planning')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->date('started_at')->nullable();
            $table->date('expected_launch_at')->nullable();
            $table->date('launched_at')->nullable();
            $table->string('drive_url', 2048)->nullable();
            $table->string('repo_url', 2048)->nullable();
            $table->string('app_url', 2048)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
