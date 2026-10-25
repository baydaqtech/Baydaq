<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A job can suggest the access role people hired into it usually get ("Branch Manager" →
     * the Branch Manager role at their branch). The role stays in Platform; HR only keeps its id.
     */
    public function up(): void
    {
        Schema::table('hr__jobs', function (Blueprint $table) {
            $table->foreignId('default_role_id')->nullable()->after('name_en_key')->constrained('platform__access_roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hr__jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_role_id');
        });
    }
};
