<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What renewing a document of a type usually costs (iqama, insurance…), and a renewal task
     * per expiring document, opened automatically at its first alert and closed when a new
     * expiry date is saved.
     */
    public function up(): void
    {
        Schema::table('hr__document_types', function (Blueprint $table) {
            $table->decimal('renewal_cost', 10, 2)->nullable()->after('alert_days');
        });

        Schema::create('hr__document_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_document_id')->constrained('hr__employee_documents')->cascadeOnDelete();
            $table->date('expires_on');
            $table->decimal('cost', 10, 2)->nullable();
            $table->string('status', 20)->default('open'); // open | done | cancelled
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_document_id', 'expires_on']);
            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr__document_renewals');
        Schema::table('hr__document_types', function (Blueprint $table) {
            $table->dropColumn('renewal_cost');
        });
    }
};
