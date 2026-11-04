<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reservations: bookable services (per company), the resources that host them at a branch
     * (rooms, chairs, courts, chalets), the staff who can perform them (HR employees, by id only),
     * each branch's opening hours and online-booking settings, and the bookings themselves.
     * A booking blocks its resource and staff member from starts_at until blocks_until
     * (the service duration plus its buffer).
     */
    public function up(): void
    {
        Schema::create('res__services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->string('name_ar');
            $table->string('name_en');
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            $table->decimal('price', 12, 2);              // including VAT
            $table->decimal('vat_rate', 5, 2)->default(15);
            $table->decimal('deposit', 12, 2)->default(0); // asked for when booking online
            $table->boolean('needs_staff')->default(false);
            $table->boolean('is_online')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('color', 7)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['client_id', 'company_id', 'is_active']);
            $table->foreign(['client_id', 'company_id'])->references(['client_id', 'id'])->on('platform__companies')->restrictOnDelete();
        });

        Schema::create('res__resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('kind', 20)->default('room');
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign(['company_id', 'branch_id'])->references(['company_id', 'id'])->on('platform__branches')->restrictOnDelete();
        });

        Schema::create('res__service_resource', function (Blueprint $table) {
            $table->foreignId('service_id')->constrained('res__services')->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained('res__resources')->cascadeOnDelete();

            $table->primary(['service_id', 'resource_id']);
        });

        Schema::create('res__service_staff', function (Blueprint $table) {
            $table->foreignId('service_id')->constrained('res__services')->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id'); // HR employee, by id only

            $table->primary(['service_id', 'employee_id']);
        });

        Schema::create('res__branch_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->primary();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('booking_token', 40)->unique();
            $table->json('hours');                                   // {"0": [["09:00","22:00"]], ...} Sunday = 0
            $table->unsignedSmallInteger('slot_minutes')->default(30);
            $table->unsignedSmallInteger('min_notice_minutes')->default(60);
            $table->unsignedSmallInteger('max_days_ahead')->default(30);
            $table->boolean('is_online')->default(false);
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('platform__branches')->cascadeOnDelete();
        });

        Schema::create('res__bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('service_id')->constrained('res__services')->restrictOnDelete();
            $table->foreignId('resource_id')->nullable()->constrained('res__resources')->nullOnDelete();
            $table->unsignedBigInteger('employee_id')->nullable(); // HR employee, by id only
            $table->string('reference', 10)->unique();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('blocks_until');
            $table->string('status', 20)->default('confirmed');
            $table->string('channel', 20)->default('staff');   // staff | online
            $table->string('customer_name');
            $table->string('customer_phone', 30);
            $table->string('customer_email')->nullable();
            $table->unsignedSmallInteger('party_size')->default(1);
            $table->decimal('price', 12, 2);
            $table->decimal('deposit', 12, 2)->default(0);
            $table->timestamp('deposit_paid_at')->nullable();
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->unsignedBigInteger('invoice_id')->nullable(); // Invoicing invoice, by id only
            $table->string('note')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'starts_at']);
            $table->index(['resource_id', 'starts_at']);
            $table->index(['employee_id', 'starts_at']);
            $table->index(['client_id', 'status']);
            $table->foreign(['company_id', 'branch_id'])->references(['company_id', 'id'])->on('platform__branches')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['res__bookings', 'res__branch_settings', 'res__service_staff', 'res__service_resource', 'res__resources', 'res__services'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
