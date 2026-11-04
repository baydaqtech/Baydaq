<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Salary and experience letters with a QR code that opens a public verification page (H12).
     * The snapshot freezes what the letter said, so a later raise doesn't change an issued letter.
     */
    public function up(): void
    {
        Schema::create('hr__letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->string('kind', 20); // salary | experience
            $table->char('language', 2);
            $table->string('addressed_to')->nullable();
            $table->string('reference', 30);
            $table->string('token', 40)->unique();
            $table->json('snapshot');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'reference']);
            $table->index(['employee_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr__letters');
    }
};
