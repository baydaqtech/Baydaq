<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scopes a user saved (pinned, with a name) or visited lately, for the ⌘K palette (H1).
     */
    public function up(): void
    {
        Schema::create('platform__scope_shortcuts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('segment', 120);
            $table->string('branches', 255)->default('');
            $table->string('name', 80)->nullable();
            $table->boolean('pinned')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'segment', 'branches']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform__scope_shortcuts');
    }
};
