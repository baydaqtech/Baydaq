<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The national address ZATCA prints on every e-invoice.
     */
    public function up(): void
    {
        Schema::table('platform__companies', function (Blueprint $table) {
            $table->string('street')->nullable()->after('qiwa_id');
            $table->string('building_number', 10)->nullable()->after('street');
            $table->string('district')->nullable()->after('building_number');
            $table->string('city')->nullable()->after('district');
            $table->string('postal_code', 10)->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('platform__companies', function (Blueprint $table) {
            $table->dropColumn(['street', 'building_number', 'district', 'city', 'postal_code']);
        });
    }
};
