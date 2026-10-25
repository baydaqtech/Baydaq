<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR rules a tenant switches on or off, and changes held for a second person's approval
     * (IBAN, salary). A held change keeps its new values encrypted until someone else approves.
     */
    public function up(): void
    {
        Schema::create('hr__settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'key']);
        });

        Schema::create('hr__change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->string('kind', 20); // iban | salary
            $table->foreignId('contract_id')->nullable()->constrained('hr__contracts')->cascadeOnDelete();
            $table->text('payload');     // encrypted new values
            $table->json('summary');     // masked before → after, safe to show
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr__change_requests');
        Schema::dropIfExists('hr__settings');
    }
};
