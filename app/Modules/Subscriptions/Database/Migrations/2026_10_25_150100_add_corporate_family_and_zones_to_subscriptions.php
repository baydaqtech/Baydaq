<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Corporate plans that HR's employees get automatically (S1); family plans shared by a few
     * members, women-only / men-only zones checked at the door, and buy-now-pay-later sales (S2).
     */
    public function up(): void
    {
        Schema::table('sub__plans', function (Blueprint $table) {
            $table->boolean('is_corporate')->default(false)->after('gender');
            $table->unsignedTinyInteger('family_size')->default(1)->after('is_corporate'); // members sharing one membership
        });

        Schema::table('sub__members', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_id')->nullable()->after('company_id'); // HR employee, by id only
            $table->unique(['company_id', 'employee_id']);
        });

        Schema::table('sub__memberships', function (Blueprint $table) {
            $table->string('bnpl_provider', 20)->nullable()->after('price'); // tamara | tabby
        });

        Schema::create('sub__membership_members', function (Blueprint $table) {
            $table->foreignId('membership_id')->constrained('sub__memberships')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('sub__members')->cascadeOnDelete();

            $table->primary(['membership_id', 'member_id']);
        });

        Schema::create('sub__zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('gender', 10)->default('any'); // any | male | female
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('platform__branches')->cascadeOnDelete();
        });

        Schema::table('sub__check_ins', function (Blueprint $table) {
            $table->foreignId('zone_id')->nullable()->after('branch_id')->constrained('sub__zones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sub__check_ins', fn (Blueprint $table) => $table->dropConstrainedForeignId('zone_id'));
        Schema::dropIfExists('sub__zones');
        Schema::dropIfExists('sub__membership_members');
        Schema::table('sub__memberships', fn (Blueprint $table) => $table->dropColumn('bnpl_provider'));
        Schema::table('sub__members', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'employee_id']);
            $table->dropColumn('employee_id');
        });
        Schema::table('sub__plans', fn (Blueprint $table) => $table->dropColumn(['is_corporate', 'family_size']));
    }
};
