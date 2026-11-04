<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The books: a chart of accounts per account (client), journal entries per company and branch
     * (the same branches as HR, POS and invoices), and purchasing: suppliers, purchase orders,
     * supplier bills and their payments.
     */
    public function up(): void
    {
        Schema::create('finance__accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('type', 20); // asset | liability | equity | revenue | expense
            $table->string('system_key', 40)->nullable(); // what modules post to, e.g. cash, sales, vat_output
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['client_id', 'code']);
            $table->unique(['client_id', 'system_key']);
        });

        Schema::create('finance__journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->date('entry_date');
            $table->string('source', 40); // manual | pos.order | invoicing.invoice | hr.payroll | hr.expense | finance.bill | finance.payment
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('memo')->nullable();
            $table->boolean('is_reversal')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'company_id', 'entry_date']);
            $table->index(['source', 'source_id']);
            $table->foreign(['client_id', 'company_id'])->references(['client_id', 'id'])->on('platform__companies')->restrictOnDelete();
        });

        Schema::create('finance__journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('finance__journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('finance__accounts')->restrictOnDelete();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->decimal('tax_base', 14, 2)->nullable(); // on VAT lines: the net amount taxed
            $table->string('memo')->nullable();

            $table->index('account_id');
        });

        Schema::create('finance__suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('vat_number', 15)->nullable();
            $table->string('cr_number', 20)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('iban', 34)->nullable();
            $table->unsignedSmallInteger('payment_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('finance__purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('supplier_id')->constrained('finance__suppliers')->restrictOnDelete();
            $table->string('number', 20);
            $table->date('order_date');
            $table->date('expected_on')->nullable();
            $table->string('status', 20)->default('draft'); // draft | approved | received | billed | cancelled
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('vat', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
        });

        Schema::create('finance__bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('supplier_id')->constrained('finance__suppliers')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('finance__purchase_orders')->nullOnDelete();
            $table->string('supplier_invoice_no', 40)->nullable();
            $table->date('bill_date');
            $table->date('due_on');
            $table->string('status', 20)->default('draft'); // draft | approved | paid | cancelled
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('vat', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('paid', 14, 2)->default(0);
            $table->string('attachment_path')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'status', 'due_on']);
        });

        // Lines of a purchase order or a bill: what was bought, which expense account it goes to.
        Schema::create('finance__document_lines', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 20); // purchase_order | bill
            $table->unsignedBigInteger('document_id');
            $table->foreignId('account_id')->nullable()->constrained('finance__accounts')->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 14, 2); // excl. VAT
            $table->decimal('vat_rate', 5, 2)->default(15);
            $table->decimal('net', 14, 2);
            $table->decimal('vat', 14, 2);

            $table->index(['document_type', 'document_id']);
        });

        Schema::create('finance__bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained('finance__bills')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('paid_on');
            $table->string('method', 20); // bank | cash
            $table->string('reference')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance__bill_payments');
        Schema::dropIfExists('finance__document_lines');
        Schema::dropIfExists('finance__bills');
        Schema::dropIfExists('finance__purchase_orders');
        Schema::dropIfExists('finance__suppliers');
        Schema::dropIfExists('finance__journal_lines');
        Schema::dropIfExists('finance__journal_entries');
        Schema::dropIfExists('finance__accounts');
    }
};
