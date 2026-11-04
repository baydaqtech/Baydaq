<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A company's closed years: the closing entry that moved the year's profit to retained earnings.
 * Once closed, no manual entry may be dated in that year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance__fiscal_closes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedSmallInteger('year');
            $table->foreignId('entry_id')->nullable()->constrained('finance__journal_entries')->nullOnDelete();
            $table->decimal('profit', 14, 2);
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'year']);
            $table->foreign(['client_id', 'company_id'])->references(['client_id', 'id'])->on('platform__companies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance__fiscal_closes');
    }
};
