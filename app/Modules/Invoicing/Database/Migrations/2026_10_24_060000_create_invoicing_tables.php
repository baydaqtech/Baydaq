<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tenant invoicing with ZATCA Phase 2: customers, invoices (standard B2B or simplified B2C,
     * plus credit and debit notes), lines, the e-invoicing units (one per issuing device, each
     * with its certificate, counter and hash chain) and every submission made to ZATCA.
     */
    public function up(): void
    {
        Schema::create('inv__customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('vat_number', 15)->nullable();
            $table->string('cr_number', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            // ZATCA address block.
            $table->string('street')->nullable();
            $table->string('building_number', 10)->nullable();
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->char('country', 2)->default('SA');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['client_id', 'name_en']);
        });

        Schema::create('inv__einvoice_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('name');
            $table->string('serial', 100); // 1-{solution}|2-{model}|3-{uuid}
            $table->string('environment', 20)->default('sandbox'); // sandbox | simulation | production
            $table->string('status', 20)->default('new'); // new | compliance | production | revoked
            $table->text('private_key')->nullable();      // encrypted PEM
            $table->text('csr')->nullable();
            $table->text('compliance_certificate')->nullable(); // encrypted, base64 X509
            $table->text('compliance_secret')->nullable();      // encrypted
            $table->string('compliance_request_id', 60)->nullable();
            $table->text('production_certificate')->nullable(); // encrypted
            $table->text('production_secret')->nullable();      // encrypted
            $table->unsignedInteger('counter')->default(0);     // ICV: strictly increasing, never reset
            $table->string('previous_hash', 128)->default('NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==');
            $table->json('compliance_checks')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'serial']);
            $table->foreign(['client_id', 'company_id'])->references(['client_id', 'id'])->on('platform__companies')->restrictOnDelete();
        });

        Schema::create('inv__invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->foreignId('customer_id')->nullable()->constrained('inv__customers')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('inv__einvoice_units')->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('number', 40)->nullable();      // assigned at issue
            $table->string('type', 20);                     // standard | simplified
            $table->string('kind', 20)->default('invoice'); // invoice | credit_note | debit_note
            $table->foreignId('related_invoice_id')->nullable()->constrained('inv__invoices')->nullOnDelete();
            $table->string('status', 20)->default('draft'); // draft | issued | cleared | reported | rejected | cancelled
            $table->date('issue_date');
            $table->time('issue_time')->nullable();
            $table->date('due_date')->nullable();
            $table->date('supply_date')->nullable();
            $table->char('currency', 3)->default('SAR');
            $table->string('payment_means', 10)->default('10'); // UN/ECE 4461: 10 cash, 30 credit, 42 bank, 48 card
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('vat', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('note')->nullable();
            $table->string('reason')->nullable(); // for notes: why the credit/debit
            $table->text('qr')->nullable();        // base64 TLV
            $table->string('hash', 128)->nullable();
            $table->unsignedInteger('counter')->nullable(); // ICV at submission
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['client_id', 'company_id', 'status']);
            $table->index(['client_id', 'issue_date']);
            $table->foreign(['client_id', 'company_id'])->references(['client_id', 'id'])->on('platform__companies')->restrictOnDelete();
        });

        Schema::create('inv__invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('inv__invoices')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 3);
            $table->string('unit_code', 10)->default('PCE');
            $table->decimal('unit_price', 14, 4);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(15);
            $table->string('vat_category', 2)->default('S'); // S standard, Z zero, E exempt, O out of scope
            $table->decimal('net', 14, 2);
            $table->decimal('vat', 14, 2);
            $table->decimal('total', 14, 2);
            $table->unsignedSmallInteger('sort_order')->default(1);
        });

        Schema::create('inv__submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('inv__invoices')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('inv__einvoice_units')->restrictOnDelete();
            $table->string('mode', 20);          // clearance | reporting
            $table->string('environment', 20);
            $table->string('status', 20);        // accepted | accepted_with_warnings | rejected | error
            $table->unsignedInteger('counter');
            $table->string('hash', 128);
            $table->longText('xml');             // the signed XML (for clearance: ZATCA's returned copy)
            $table->json('response')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['invoice_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inv__submissions');
        Schema::dropIfExists('inv__invoice_lines');
        Schema::dropIfExists('inv__invoices');
        Schema::dropIfExists('inv__einvoice_units');
        Schema::dropIfExists('inv__customers');
    }
};
