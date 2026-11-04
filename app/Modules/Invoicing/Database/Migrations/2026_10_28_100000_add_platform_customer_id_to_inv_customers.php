<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A buyer with a phone is also the account's shared customer (one per phone), so their invoices
 * show on the customer and the privacy toolkit can export and erase them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inv__customers', function (Blueprint $table) {
            $table->unsignedBigInteger('platform_customer_id')->nullable()->after('client_id');
            $table->index('platform_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('inv__customers', function (Blueprint $table) {
            $table->dropIndex(['platform_customer_id']);
            $table->dropColumn('platform_customer_id');
        });
    }
};
