<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Point of sale: the product catalog (per company, optionally per brand, with per-branch
     * price and availability overrides and modifiers), registers with cash shifts, and orders
     * with their lines and payments. Paying an order issues a simplified invoice through the
     * Invoicing module's port; the invoice id is kept on the order.
     */
    public function up(): void
    {
        Schema::create('pos__categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('color', 7)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign(['client_id', 'company_id'])->references(['client_id', 'id'])->on('platform__companies')->restrictOnDelete();
            $table->foreign('brand_id')->references('id')->on('platform__brands')->nullOnDelete();
        });

        Schema::create('pos__products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->foreignId('category_id')->nullable()->constrained('pos__categories')->nullOnDelete();
            $table->string('sku', 40)->nullable();
            $table->string('name_ar');
            $table->string('name_en');
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->decimal('price', 12, 2);            // including VAT, as menus show it
            $table->decimal('vat_rate', 5, 2)->default(15);
            $table->string('image')->nullable();
            // SFDA menu labelling.
            $table->unsignedSmallInteger('calories')->nullable();
            $table->boolean('high_salt')->default(false);
            $table->unsignedSmallInteger('caffeine_mg')->nullable();
            $table->json('allergens')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('sold_by_weight')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'sku']);
            $table->index(['client_id', 'company_id', 'is_active']);
            $table->foreign(['client_id', 'company_id'])->references(['client_id', 'id'])->on('platform__companies')->restrictOnDelete();
        });

        Schema::create('pos__branch_products', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('pos__products')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('platform__branches')->cascadeOnDelete();
            $table->decimal('price', 12, 2)->nullable(); // override
            $table->boolean('is_available')->default(true);

            $table->primary(['product_id', 'branch_id']);
        });

        Schema::create('pos__modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->string('name_ar');
            $table->string('name_en');
            $table->unsignedTinyInteger('min')->default(0);
            $table->unsignedTinyInteger('max')->default(1);
            $table->timestamps();

            $table->foreign(['client_id', 'company_id'])->references(['client_id', 'id'])->on('platform__companies')->restrictOnDelete();
        });

        Schema::create('pos__modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('pos__modifier_groups')->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('pos__product_modifier_group', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('pos__products')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('pos__modifier_groups')->cascadeOnDelete();

            $table->primary(['product_id', 'group_id']);
        });

        Schema::create('pos__registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');
            $table->string('name', 60);
            $table->unsignedBigInteger('einvoice_unit_id')->nullable(); // Invoicing unit, referenced by id only
            $table->string('menu_token', 40)->unique();              // public QR menu
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'name']);
            $table->foreign(['company_id', 'branch_id'])->references(['company_id', 'id'])->on('platform__branches')->restrictOnDelete();
        });

        Schema::create('pos__cashiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('employee_id')->nullable(); // HR employee, by id only
            $table->string('name');
            $table->string('pin_hash');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'employee_id']);
        });

        Schema::create('pos__shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('register_id')->constrained('pos__registers')->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('pos__cashiers')->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_cash', 12, 2)->default(0);
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('sales_total', 14, 2)->default(0);
            $table->timestamps();

            $table->index(['register_id', 'closed_at']);
        });

        Schema::create('pos__orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('register_id')->nullable()->constrained('pos__registers')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('pos__shifts')->nullOnDelete();
            $table->foreignId('cashier_id')->nullable()->constrained('pos__cashiers')->nullOnDelete();
            $table->unsignedInteger('number');                 // daily per branch
            $table->date('order_date');
            $table->string('channel', 20)->default('pos');     // pos | qr_menu
            $table->string('type', 20)->default('takeaway');   // dine_in | takeaway | delivery
            $table->string('status', 20)->default('open');     // open | paid | void
            $table->string('kitchen_status', 20)->default('new'); // new | preparing | ready | served
            $table->string('table_no', 10)->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);   // excl. VAT
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('vat', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);      // incl. VAT
            $table->unsignedBigInteger('invoice_id')->nullable(); // Invoicing invoice, by id only
            $table->string('note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'order_date', 'number']);
            $table->index(['client_id', 'branch_id', 'status']);
            $table->foreign(['company_id', 'branch_id'])->references(['company_id', 'id'])->on('platform__branches')->restrictOnDelete();
        });

        Schema::create('pos__order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('pos__orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('pos__products')->nullOnDelete();
            $table->string('name');                         // as sold
            $table->decimal('quantity', 10, 3);
            $table->decimal('unit_price', 12, 2);           // incl. VAT, with modifiers
            $table->decimal('vat_rate', 5, 2);
            $table->decimal('net', 12, 2);
            $table->decimal('vat', 12, 2);
            $table->decimal('total', 12, 2);
            $table->json('modifiers')->nullable();          // [{name, price}]
            $table->string('note')->nullable();
        });

        Schema::create('pos__payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('pos__orders')->cascadeOnDelete();
            $table->string('method', 20); // cash | mada | card | stc_pay | transfer
            $table->decimal('amount', 12, 2);
            $table->string('reference')->nullable();
            $table->timestamp('paid_at');
        });
    }

    public function down(): void
    {
        foreach (['pos__payments', 'pos__order_lines', 'pos__orders', 'pos__shifts', 'pos__cashiers', 'pos__registers', 'pos__product_modifier_group', 'pos__modifiers', 'pos__modifier_groups', 'pos__branch_products', 'pos__products', 'pos__categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
