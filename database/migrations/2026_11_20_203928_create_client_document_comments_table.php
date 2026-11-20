<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comments on a client document, from the client's logins and from the Baydaq team.
     */
    public function up(): void
    {
        Schema::create('client_document_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('by_team')->default(false);
            $table->text('body');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_document_comments');
    }
};
