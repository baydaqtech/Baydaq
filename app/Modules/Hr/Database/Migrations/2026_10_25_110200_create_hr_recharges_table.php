<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a cross-company secondment cost the home company in a month, billed to the host
     * company once (the invoice lives in Invoicing; HR keeps only its id).
     */
    public function up(): void
    {
        Schema::create('hr__recharges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('movement_id')->constrained('hr__employee_movements')->cascadeOnDelete();
            $table->date('month');
            $table->unsignedBigInteger('from_company_id');
            $table->unsignedBigInteger('to_company_id');
            $table->unsignedSmallInteger('days');
            $table->decimal('amount', 12, 2);
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->timestamps();

            $table->unique(['movement_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr__recharges');
    }
};
