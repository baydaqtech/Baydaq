<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expense claims: an employee spends for the company (fuel, a client lunch, supplies), sends
     * the receipt from the app, and is paid back once approved. VAT on a proper VAT invoice is
     * claimable as input VAT.
     */
    public function up(): void
    {
        Schema::create('hr__expense_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');
            $table->date('spent_on');
            $table->string('category', 30); // travel | fuel | phone | meals | supplies | maintenance | other_expense
            $table->decimal('amount', 12, 2); // what was paid, VAT included
            $table->decimal('vat', 12, 2)->default(0);
            $table->string('merchant')->nullable();
            $table->string('merchant_vat_number', 15)->nullable();
            $table->string('description')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('status', 20)->default('submitted'); // submitted | approved | rejected | paid
            $table->string('decision_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->date('paid_on')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['employee_id', 'spent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr__expense_claims');
    }
};
