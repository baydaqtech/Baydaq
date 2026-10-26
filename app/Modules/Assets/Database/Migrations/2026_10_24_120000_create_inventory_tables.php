<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inventory next to the tagged assets: units of measure (pieces, ml, kg, km…), stock items and
     * their variants (Juice → Mango 330 ml, Milk 400 ml) with pack sizes, warehouses, and stock that
     * sits either in a warehouse or with an employee. Every change is a line in the moves ledger.
     * Transfers move stock between warehouses; handovers move it between a warehouse and a person
     * or between people. Whatever does not arrive or come back is an incident (broken, lost,
     * stolen…) with photos, resolved later by management.
     */
    public function up(): void
    {
        Schema::create('ast__units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('symbol', 12);
            $table->string('dimension', 10);                 // count | volume | weight | length
            $table->decimal('factor', 18, 6)->default(1);     // in the dimension's smallest unit: ml, g, mm, piece
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['client_id', 'symbol']);
        });

        Schema::create('ast__items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('ast__categories')->nullOnDelete();
            $table->foreignId('unit_id')->constrained('ast__units')->restrictOnDelete(); // stock is counted in this unit
            $table->string('name_ar');
            $table->string('name_en');
            $table->text('description')->nullable();
            $table->decimal('min_stock', 14, 3)->nullable();  // per warehouse, for low-stock alerts
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ast__item_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('ast__items')->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('sku', 40)->nullable();
            $table->string('barcode', 64)->nullable();
            $table->decimal('content', 14, 3)->nullable();    // e.g. 330 …
            $table->foreignId('content_unit_id')->nullable()->constrained('ast__units')->nullOnDelete(); // … ml
            $table->decimal('unit_cost', 14, 4)->default(0);  // per stock unit, for valuing stock and incidents
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('barcode');
            $table->index('sku');
        });

        Schema::create('ast__item_packs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('ast__items')->cascadeOnDelete();
            $table->string('name_ar');                        // كرتون
            $table->string('name_en');                        // Carton
            $table->decimal('factor', 14, 3);                 // stock units in one pack: 24
        });

        Schema::create('ast__warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');
            $table->string('code', 20);
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('kind', 20)->default('store');     // main | store | vehicle | event
            $table->unsignedBigInteger('keeper_employee_id')->nullable(); // HR employee, by id only
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['client_id', 'code']);
            $table->foreign(['company_id', 'branch_id'])->references(['company_id', 'id'])->on('platform__branches')->restrictOnDelete();
        });

        Schema::table('ast__assets', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('branch_id')->constrained('ast__warehouses')->nullOnDelete();
        });

        // Quantities on hand per holder: a warehouse, or an employee who received a handover.
        Schema::create('ast__stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('ast__item_variants')->cascadeOnDelete();
            $table->string('holder_type', 10);                // warehouse | employee
            $table->unsignedBigInteger('holder_id');
            $table->decimal('quantity', 14, 3)->default(0);
            $table->timestamps();

            $table->unique(['holder_type', 'holder_id', 'variant_id']);
        });

        Schema::create('ast__transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('number', 20);
            $table->foreignId('from_warehouse_id')->constrained('ast__warehouses')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('ast__warehouses')->restrictOnDelete();
            $table->string('status', 20)->default('draft');  // draft | in_transit | received | cancelled
            $table->string('note')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'number']);
        });

        // Handovers: warehouse → employee (issue), employee → warehouse (return), employee → employee.
        Schema::create('ast__handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->string('number', 20);
            $table->string('from_type', 10);                  // warehouse | employee
            $table->unsignedBigInteger('from_id');
            $table->string('to_type', 10);
            $table->unsignedBigInteger('to_id');
            $table->string('status', 20)->default('pending'); // pending (waiting for the receiver) | accepted | rejected | cancelled
            $table->string('purpose')->nullable();            // "Wedding hall event", "Shift change"
            $table->string('note')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('channel', 10)->default('web');    // web | mobile
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('accepted_by')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'number']);
            $table->index(['to_type', 'to_id', 'status']);
            $table->index(['from_type', 'from_id', 'status']);
        });

        // One line per variant (a quantity) or per tagged asset (always one) on a transfer or handover.
        Schema::create('ast__movement_lines', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 10);              // transfer | handover
            $table->unsignedBigInteger('document_id');
            $table->foreignId('variant_id')->nullable()->constrained('ast__item_variants')->restrictOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('ast__assets')->restrictOnDelete();
            $table->decimal('quantity', 14, 3);               // sent, in stock units
            $table->decimal('received', 14, 3)->nullable();   // counted by the receiver
            $table->string('entered_unit')->nullable();       // how the sender typed it: "2 × Carton"
            $table->string('condition')->nullable();
            $table->string('note')->nullable();

            $table->index(['document_type', 'document_id']);
        });

        Schema::create('ast__incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->string('number', 20);
            $table->string('kind', 20);                       // broken | lost | stolen | damaged | consumed | expired | missing | other
            $table->string('source_type', 10);                // transfer | handover | report | count
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('variant_id')->nullable()->constrained('ast__item_variants')->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('ast__assets')->nullOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('value', 14, 2)->default(0);      // quantity × unit cost when it happened
            $table->string('holder_type', 10)->nullable();    // where it was when it happened
            $table->unsignedBigInteger('holder_id')->nullable();
            $table->unsignedBigInteger('responsible_employee_id')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('open');    // open | resolved
            $table->string('resolution', 30)->nullable();     // written_off | charged | recovered | insurance | other
            $table->decimal('charged_amount', 14, 2)->nullable();
            $table->string('resolution_note')->nullable();
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'number']);
            $table->index(['client_id', 'status']);
        });

        Schema::create('ast__attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('attachable_type', 20);            // handover | transfer | incident | maintenance | asset
            $table->unsignedBigInteger('attachable_id');
            $table->string('disk', 20);
            $table->string('path');
            $table->string('mime', 60);
            $table->unsignedInteger('size');
            $table->string('stage', 20)->nullable();          // sent | received | returned | incident
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id']);
        });

        Schema::create('ast__stock_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('ast__item_variants')->cascadeOnDelete();
            $table->string('from_type', 10)->nullable();      // null = came from outside (purchase, opening stock)
            $table->unsignedBigInteger('from_id')->nullable();
            $table->string('to_type', 10)->nullable();        // null = left the books (incident, consumed)
            $table->unsignedBigInteger('to_id')->nullable();
            $table->decimal('quantity', 14, 3);
            $table->string('reason', 20);                     // receipt | transfer | handover | incident | adjustment | in_transit
            $table->string('document_type', 10)->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('moved_at');

            $table->index(['variant_id', 'moved_at']);
            $table->index(['document_type', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ast__assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });

        foreach (['ast__stock_moves', 'ast__attachments', 'ast__incidents', 'ast__movement_lines', 'ast__handovers', 'ast__transfers', 'ast__stock', 'ast__warehouses', 'ast__item_packs', 'ast__item_variants', 'ast__items', 'ast__units'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
