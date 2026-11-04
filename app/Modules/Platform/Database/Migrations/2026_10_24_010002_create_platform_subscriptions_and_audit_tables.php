<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform__subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('module', 20);
            $table->string('status', 20)->default('active');
            $table->string('billing_unit', 20);
            // What the client bought: seats for HR, registers for POS, branches for bookings...
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'module', 'status']);
        });

        Schema::create('platform__audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->string('event', 40);
            $table->json('changes')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at');

            $table->index(['client_id', 'auditable_type', 'auditable_id', 'created_at'], 'platform__audit_events_auditable_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform__audit_events');
        Schema::dropIfExists('platform__subscriptions');
    }
};
