<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The employee's own short PIN (hashed), used to sign in at tills and devices (P1). */
    public function up(): void
    {
        Schema::table('hr__employees', function (Blueprint $table) {
            $table->string('pin_hash')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('hr__employees', function (Blueprint $table) {
            $table->dropColumn('pin_hash');
        });
    }
};
