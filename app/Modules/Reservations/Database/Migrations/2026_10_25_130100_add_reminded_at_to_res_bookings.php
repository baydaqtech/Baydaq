<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** When the day-before WhatsApp reminder went out, so it goes out once. */
    public function up(): void
    {
        Schema::table('res__bookings', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable()->after('deposit_paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('res__bookings', function (Blueprint $table) {
            $table->dropColumn('reminded_at');
        });
    }
};
