<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stays (R2): a service can be booked by the night (a chalet, a room) from a check-in time to
     * a check-out time, together with the staff it needs; the booking keeps how many nights.
     */
    public function up(): void
    {
        Schema::table('res__services', function (Blueprint $table) {
            $table->boolean('is_stay')->default(false)->after('needs_staff');
            $table->time('check_in_time')->nullable()->after('is_stay');
            $table->time('check_out_time')->nullable()->after('check_in_time');
        });

        Schema::table('res__bookings', function (Blueprint $table) {
            $table->unsignedSmallInteger('nights')->default(0)->after('party_size'); // 0 for timed bookings
        });
    }

    public function down(): void
    {
        Schema::table('res__bookings', fn (Blueprint $table) => $table->dropColumn('nights'));
        Schema::table('res__services', fn (Blueprint $table) => $table->dropColumn(['is_stay', 'check_in_time', 'check_out_time']));
    }
};
