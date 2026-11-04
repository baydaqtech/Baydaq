<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ZATCA e-invoice issued for a Baydaq invoice through Baydaq's own suite (I4).
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('einvoice_id')->nullable()->after('paid_at');
            $table->string('einvoice_number', 40)->nullable()->after('einvoice_id');
            $table->string('einvoice_status', 20)->nullable()->after('einvoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['einvoice_id', 'einvoice_number', 'einvoice_status']);
        });
    }
};
