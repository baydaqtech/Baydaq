<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily usage of a technical service on a client's project (emails sent, OTPs, messages...).
     * One row per service per day; the cost is fixed at recording time from the service's price.
     */
    public function up(): void
    {
        Schema::create('service_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_service_id')->constrained('project_services')->cascadeOnDelete();
            $table->date('usage_date');
            $table->decimal('quantity', 14, 2)->default(0);
            $table->decimal('cost', 12, 2)->default(0);
            $table->string('source', 20)->default('api'); // api | manual
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['project_service_id', 'usage_date']);
        });

        Schema::table('project_services', function (Blueprint $table) {
            $table->timestamp('api_key_issued_at')->nullable()->after('api_key');
            $table->timestamp('last_used_at')->nullable()->after('api_key_issued_at');
        });
    }

    public function down(): void
    {
        Schema::table('project_services', function (Blueprint $table) {
            $table->dropColumn(['api_key_issued_at', 'last_used_at']);
        });
        Schema::dropIfExists('service_usage');
    }
};
