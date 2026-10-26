<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Outgoing messages (WhatsApp through Baydaq's own service) and online payments (a hosted
     * payment page per payable thing). Both are kernel services the modules reach via ports.
     */
    public function up(): void
    {
        Schema::create('platform__messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 20)->default('whatsapp');
            $table->string('to', 30);
            $table->string('template', 60);
            $table->json('params')->nullable();
            $table->text('body');
            $table->string('locale', 5)->default('ar');
            $table->string('context', 80)->nullable(); // e.g. hr.leave:12
            $table->string('status', 20); // logged | sent | failed
            $table->string('provider_id')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'created_at']);
        });

        Schema::create('platform__payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('token', 40)->unique();
            $table->string('reference', 80);    // what is being paid, e.g. invoicing.invoice:34
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('SAR');
            $table->string('description');
            $table->string('provider', 20);
            $table->string('provider_id')->nullable();
            $table->string('checkout_url', 500)->nullable();
            $table->string('status', 20)->default('pending'); // pending | paid | failed | expired
            $table->string('return_url', 500)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('reference');
            $table->index(['provider', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform__payments');
        Schema::dropIfExists('platform__messages');
    }
};
