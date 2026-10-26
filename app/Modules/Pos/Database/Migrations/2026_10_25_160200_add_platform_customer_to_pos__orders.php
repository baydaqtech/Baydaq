<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The account-wide customer record this row belongs to (X2), resolved by phone.
     */
    public function up(): void
    {
        Schema::table('pos__orders', function (Blueprint $table) {
            $table->unsignedBigInteger('platform_customer_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('pos__orders', function (Blueprint $table) {
            $table->dropColumn('platform_customer_id');
        });
    }
};
