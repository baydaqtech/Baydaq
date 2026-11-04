<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One final settlement per leaver: the end-of-service award, what was recovered from open
     * salary advances, and what is left to pay (or to collect when the advances were larger).
     */
    public function up(): void
    {
        Schema::create('hr__final_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->unique()->constrained('hr__employees')->cascadeOnDelete();
            $table->string('reason', 20);
            $table->date('last_day');
            $table->decimal('eosb_award', 12, 2);
            $table->decimal('advances_recovered', 12, 2);
            $table->decimal('advances_left', 12, 2);
            $table->decimal('net_payable', 12, 2);
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr__final_settlements');
    }
};
