<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Where the punch selfie is kept on the private HR disk, when the tenant asks for one. */
    public function up(): void
    {
        Schema::table('hr__attendance_punches', function (Blueprint $table) {
            $table->string('selfie_path')->nullable()->after('device_id');
        });
    }

    public function down(): void
    {
        Schema::table('hr__attendance_punches', function (Blueprint $table) {
            $table->dropColumn('selfie_path');
        });
    }
};
