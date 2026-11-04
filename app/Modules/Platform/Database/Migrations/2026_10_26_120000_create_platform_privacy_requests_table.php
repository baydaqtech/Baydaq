<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PDPL: requests from people about their personal data (a copy, a correction, erasure, or
     * stopping marketing), each with the 30-day deadline to answer; and when a customer was erased.
     */
    public function up(): void
    {
        Schema::create('platform__privacy_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20); // access | correction | erasure | objection
            $table->string('subject', 20)->default('customer'); // customer | employee | other
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('name');
            $table->string('contact', 120); // phone or email to answer on
            $table->date('received_on');
            $table->date('due_on');
            $table->string('status', 20)->default('open'); // open | done | refused
            $table->text('details')->nullable();
            $table->text('outcome')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status', 'due_on']);
        });

        Schema::table('platform__customers', function (Blueprint $table) {
            $table->timestamp('erased_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('platform__customers', function (Blueprint $table) {
            $table->dropColumn('erased_at');
        });

        Schema::dropIfExists('platform__privacy_requests');
    }
};
