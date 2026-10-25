<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A customer can be another company of the same owner. Invoices to it are intercompany, so
     * group reports can leave them out (they cancel out across the group). Invoices also record
     * when they were paid through a payment link.
     */
    public function up(): void
    {
        Schema::table('inv__customers', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('client_id');
            $table->unique(['client_id', 'company_id']);
        });

        Schema::table('inv__invoices', function (Blueprint $table) {
            $table->boolean('intercompany')->default(false)->after('kind');
            $table->string('source', 40)->nullable()->after('intercompany'); // module that raised it, e.g. hr.recharge
            $table->timestamp('paid_at')->nullable()->after('issued_at'); // paid through a payment link
        });
    }

    public function down(): void
    {
        Schema::table('inv__invoices', function (Blueprint $table) {
            $table->dropColumn(['intercompany', 'source', 'paid_at']);
        });

        Schema::table('inv__customers', function (Blueprint $table) {
            $table->dropUnique(['client_id', 'company_id']);
            $table->dropColumn('company_id');
        });
    }
};
