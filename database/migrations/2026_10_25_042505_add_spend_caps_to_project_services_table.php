<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A monthly spend cap per project service, and the last alert sent against it (B2).
     */
    public function up(): void
    {
        Schema::table('project_services', function (Blueprint $table) {
            $table->decimal('monthly_cap', 12, 2)->nullable()->after('price');
            $table->char('cap_alert_month', 7)->nullable()->after('monthly_cap');
            $table->unsignedTinyInteger('cap_alert_level')->nullable()->after('cap_alert_month');
        });
    }

    public function down(): void
    {
        Schema::table('project_services', function (Blueprint $table) {
            $table->dropColumn(['monthly_cap', 'cap_alert_month', 'cap_alert_level']);
        });
    }
};
