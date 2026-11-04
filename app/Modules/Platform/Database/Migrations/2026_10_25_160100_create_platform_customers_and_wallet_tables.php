<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One customer record per phone number across POS, bookings and memberships, with a wallet
     * that tops up, pays and earns loyalty credit anywhere in the account (X2).
     */
    public function up(): void
    {
        Schema::create('platform__customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->decimal('wallet_balance', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'phone']);
        });

        Schema::create('platform__wallet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('platform__customers')->cascadeOnDelete();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->decimal('amount', 12, 2); // + credit, − spend
            $table->decimal('balance_after', 12, 2);
            $table->string('kind', 20); // top_up | spend | refund | reward | adjustment
            $table->string('source', 40); // e.g. pos.order, suite.manual
            $table->string('reference', 80)->nullable();
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['customer_id', 'id']);
            $table->unique(['source', 'reference', 'kind']);
        });

        Schema::table('platform__companies', function (Blueprint $table) {
            $table->decimal('loyalty_percent', 5, 2)->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('platform__companies', function (Blueprint $table) {
            $table->dropColumn('loyalty_percent');
        });
        Schema::dropIfExists('platform__wallet_entries');
        Schema::dropIfExists('platform__customers');
    }
};
