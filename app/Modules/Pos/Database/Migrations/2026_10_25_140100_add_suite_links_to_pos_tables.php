<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * POS joins up with the rest of the suite: a register's hardware is an asset (P5) and can
     * require the cashier to be checked in (P1); cashiers may earn a commission (P3); shifts
     * remember what they sent to payroll (P2, P3); orders can belong to one brand (P4) or be a
     * staff meal charged to payroll (P3); products carry a cost (MN3) and salt in grams (MN1).
     */
    public function up(): void
    {
        Schema::table('pos__products', function (Blueprint $table) {
            $table->decimal('cost', 12, 2)->nullable()->after('price');          // excl. VAT, for menu engineering
            $table->decimal('salt_g', 6, 2)->nullable()->after('high_salt');
        });

        Schema::table('pos__registers', function (Blueprint $table) {
            $table->unsignedBigInteger('asset_id')->nullable()->after('einvoice_unit_id'); // Assets asset, by id only
            $table->boolean('require_check_in')->default(true)->after('asset_id');
        });

        Schema::table('pos__cashiers', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->default(0)->after('pin_hash'); // % of the shift's sales
        });

        Schema::table('pos__shifts', function (Blueprint $table) {
            $table->boolean('shortage_sent')->default(false)->after('sales_total');
            $table->unsignedBigInteger('commission_bonus_id')->nullable()->after('shortage_sent'); // HR bonus, by id only
        });

        Schema::table('pos__orders', function (Blueprint $table) {
            $table->unsignedBigInteger('brand_id')->nullable()->after('branch_id');
            $table->unsignedBigInteger('staff_employee_id')->nullable()->after('cashier_id'); // HR employee for a staff meal
            $table->index(['branch_id', 'brand_id', 'order_date']);
        });
    }

    public function down(): void
    {
        Schema::table('pos__orders', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'brand_id', 'order_date']);
            $table->dropColumn(['brand_id', 'staff_employee_id']);
        });
        Schema::table('pos__shifts', fn (Blueprint $table) => $table->dropColumn(['shortage_sent', 'commission_bonus_id']));
        Schema::table('pos__cashiers', fn (Blueprint $table) => $table->dropColumn('commission_rate'));
        Schema::table('pos__registers', fn (Blueprint $table) => $table->dropColumn(['asset_id', 'require_check_in']));
        Schema::table('pos__products', fn (Blueprint $table) => $table->dropColumn(['cost', 'salt_g']));
    }
};
