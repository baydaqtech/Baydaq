<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Builds and store versions of a project, shown to the client on its release timeline (B3).
     */
    public function up(): void
    {
        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('version', 40);
            $table->string('platform', 20);
            $table->string('channel', 20);
            $table->string('url', 2048)->nullable();
            $table->text('notes')->nullable();
            $table->date('released_at');
            $table->timestamps();

            $table->index(['project_id', 'released_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('releases');
    }
};
