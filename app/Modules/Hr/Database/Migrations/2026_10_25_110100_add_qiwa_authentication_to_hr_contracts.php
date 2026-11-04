<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether the contract is documented on Qiwa: from 2026 a Saudi counts in full toward
     * Nitaqat only with a Qiwa-authenticated contract paying at least SAR 4,000.
     */
    public function up(): void
    {
        Schema::table('hr__contracts', function (Blueprint $table) {
            $table->boolean('qiwa_authenticated')->default(false)->after('basic_salary');
        });
    }

    public function down(): void
    {
        Schema::table('hr__contracts', function (Blueprint $table) {
            $table->dropColumn('qiwa_authenticated');
        });
    }
};
