<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refunds of paid orders: which lines and quantities came back, how the money went back (cash
 * from the till, card, or the customer's wallet), and the credit note behind it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos__refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('order_id')->constrained('pos__orders')->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('pos__shifts')->nullOnDelete(); // the till cash came out of
            $table->string('method', 20); // cash | card | wallet
            $table->decimal('subtotal', 14, 2);
            $table->decimal('vat', 14, 2);
            $table->decimal('total', 14, 2);
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable(); // Invoicing credit note, by id only
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'branch_id', 'created_at']);
            $table->foreign(['company_id', 'branch_id'])->references(['company_id', 'id'])->on('platform__branches')->restrictOnDelete();
        });

        Schema::create('pos__refund_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')->constrained('pos__refunds')->cascadeOnDelete();
            $table->foreignId('order_line_id')->constrained('pos__order_lines')->restrictOnDelete();
            $table->decimal('quantity', 10, 3);
            $table->decimal('net', 12, 2);
            $table->decimal('vat', 12, 2);
            $table->decimal('total', 12, 2);
        });

        Schema::table('pos__orders', function (Blueprint $table) {
            $table->decimal('refunded', 14, 2)->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('pos__orders', function (Blueprint $table) {
            $table->dropColumn('refunded');
        });
        Schema::dropIfExists('pos__refund_lines');
        Schema::dropIfExists('pos__refunds');
    }
};
