<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Client registration: the owner's customers sign up by phone at a branch, and are counted as
     * clients of that branch, its company or its organization, as each company chooses. The
     * customer record stays one per phone; a registration says where they joined.
     */
    public function up(): void
    {
        Schema::create('platform__customer_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('platform__customers')->cascadeOnDelete();
            $table->string('scope_type', 15); // organization | company | branch
            $table->unsignedBigInteger('scope_id');
            $table->unsignedBigInteger('branch_id')->nullable(); // where they signed up
            $table->string('source', 15); // staff | pos | self | booking | membership
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('marketing_consent_at')->nullable(); // PDPL: offers by WhatsApp/SMS only with consent
            $table->timestamps();

            $table->unique(['customer_id', 'scope_type', 'scope_id'], 'platform__customer_registrations_scope_unique');
            $table->index(['client_id', 'scope_type', 'scope_id'], 'platform__customer_registrations_client_scope_index');
        });

        Schema::table('platform__customers', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('email');
            $table->string('gender', 10)->nullable()->after('birth_date');
        });

        Schema::table('platform__companies', function (Blueprint $table) {
            $table->string('client_scope', 15)->default('company')->after('loyalty_percent'); // where clients register
        });

        Schema::table('platform__branches', function (Blueprint $table) {
            $table->char('join_token', 32)->nullable()->unique(); // the branch's self-registration QR
        });
    }

    public function down(): void
    {
        Schema::table('platform__branches', function (Blueprint $table) {
            $table->dropColumn('join_token');
        });
        Schema::table('platform__companies', function (Blueprint $table) {
            $table->dropColumn('client_scope');
        });
        Schema::table('platform__customers', function (Blueprint $table) {
            $table->dropColumn(['birth_date', 'gender']);
        });
        Schema::dropIfExists('platform__customer_registrations');
    }
};
