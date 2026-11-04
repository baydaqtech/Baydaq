<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each source (a sale, a payroll, a bill…) is posted once and reversed at most once, enforced by
     * the database so two concurrent posts cannot both succeed. Manual entries have no source id.
     */
    public function up(): void
    {
        Schema::table('finance__journal_entries', function (Blueprint $table) {
            $table->unique(['source', 'source_id', 'is_reversal'], 'finance__journal_entries_source_once');
        });
    }

    public function down(): void
    {
        Schema::table('finance__journal_entries', function (Blueprint $table) {
            $table->dropUnique('finance__journal_entries_source_once');
        });
    }
};
