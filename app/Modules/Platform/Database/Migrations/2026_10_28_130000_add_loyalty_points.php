<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loyalty points beside the wallet: each company sets how many points a riyal earns, what a point
 * is worth when redeemed and its tiers; a customer keeps one balance across the account, and
 * every change is an entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform__companies', function (Blueprint $table) {
            $table->decimal('points_per_riyal', 6, 2)->nullable()->after('loyalty_percent'); // null or 0 = no points
            $table->decimal('point_value', 8, 4)->nullable()->after('points_per_riyal');     // SAR per point when redeemed
            $table->json('points_tiers')->nullable()->after('point_value');                  // [{name_ar, name_en, from, multiplier}]
        });

        Schema::table('platform__customers', function (Blueprint $table) {
            $table->unsignedInteger('points')->default(0)->after('wallet_balance');
            $table->unsignedInteger('lifetime_points')->default(0)->after('points');
        });

        Schema::create('platform__point_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('platform__customers')->cascadeOnDelete();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->integer('points'); // + earned, − redeemed or taken back
            $table->unsignedInteger('balance_after');
            $table->string('kind', 20); // earn | redeem | reverse | adjust
            $table->string('source', 40);
            $table->string('reference', 64)->nullable();
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['source', 'reference', 'kind'], 'platform_point_entries_once');
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform__point_entries');
        Schema::table('platform__customers', function (Blueprint $table) {
            $table->dropColumn(['points', 'lifetime_points']);
        });
        Schema::table('platform__companies', function (Blueprint $table) {
            $table->dropColumn(['points_per_riyal', 'point_value', 'points_tiers']);
        });
    }
};
