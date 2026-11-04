<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payable rounding (UBL BT-114): sales priced including VAT can land a halala or two away from
     * the computed total once VAT is rounded per line; the rounding brings the payable amount back
     * to what the customer actually paid.
     */
    public function up(): void
    {
        Schema::table('inv__invoices', function (Blueprint $table) {
            $table->decimal('rounding', 8, 2)->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('inv__invoices', function (Blueprint $table) {
            $table->dropColumn('rounding');
        });
    }
};
