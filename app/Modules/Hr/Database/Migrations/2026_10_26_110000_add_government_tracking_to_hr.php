<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Government tracking: the first GOSI registration date (which GOSI scheme applies), each
     * contract's documentation state on Qiwa, and the exit/re-entry visas issued through Muqeem.
     */
    public function up(): void
    {
        Schema::table('hr__employees', function (Blueprint $table) {
            $table->date('gosi_registered_on')->nullable()->after('hired_on');
        });

        Schema::table('hr__contracts', function (Blueprint $table) {
            $table->string('qiwa_status', 20)->default('not_started'); // not_started | sent | documented | rejected
            $table->string('qiwa_reference', 40)->nullable();
            $table->date('qiwa_documented_on')->nullable();
        });

        DB::table('hr__contracts')->where('qiwa_authenticated', true)->update(['qiwa_status' => 'documented']);

        Schema::create('hr__travel_visas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr__employees')->cascadeOnDelete();
            $table->string('kind', 20); // single | multiple | final_exit
            $table->string('number', 30)->nullable();
            $table->date('issued_on');
            $table->date('expires_on'); // leave by this date
            $table->unsignedSmallInteger('duration_days')->nullable(); // allowed stay abroad
            $table->date('departed_on')->nullable();
            $table->date('returned_on')->nullable();
            $table->string('status', 20)->default('issued'); // issued | abroad | returned | cancelled | not_returned
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr__travel_visas');

        Schema::table('hr__contracts', function (Blueprint $table) {
            $table->dropColumn(['qiwa_status', 'qiwa_reference', 'qiwa_documented_on']);
        });

        Schema::table('hr__employees', function (Blueprint $table) {
            $table->dropColumn('gosi_registered_on');
        });
    }
};
