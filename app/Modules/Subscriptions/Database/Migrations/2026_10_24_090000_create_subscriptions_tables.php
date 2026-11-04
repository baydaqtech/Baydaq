<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Memberships (gyms, clubs, classes): a company's plans, its members with a card code for the
     * door, the memberships they buy (dated, optionally with a number of visits), freezes that
     * push the end date out, and every check-in, allowed or not.
     */
    public function up(): void
    {
        Schema::create('sub__plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->string('name_ar');
            $table->string('name_en');
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('period_unit', 10)->default('month'); // day | week | month | year
            $table->unsignedSmallInteger('period_count')->default(1);
            $table->decimal('price', 12, 2);                      // including VAT
            $table->decimal('vat_rate', 5, 2)->default(15);
            $table->unsignedSmallInteger('sessions')->nullable(); // visits included; null = unlimited
            $table->unsignedSmallInteger('freeze_days')->default(0);
            $table->string('gender', 10)->default('any');         // any | male | female
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign(['client_id', 'company_id'])->references(['client_id', 'id'])->on('platform__companies')->restrictOnDelete();
        });

        Schema::create('sub__plan_branch', function (Blueprint $table) {
            $table->foreignId('plan_id')->constrained('sub__plans')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('platform__branches')->cascadeOnDelete();

            $table->primary(['plan_id', 'branch_id']);
        });

        Schema::create('sub__members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->string('number', 20);
            $table->string('name');
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('card_token', 40)->unique();
            $table->string('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->unique(['company_id', 'phone']);
            $table->foreign(['client_id', 'company_id'])->references(['client_id', 'id'])->on('platform__companies')->restrictOnDelete();
        });

        Schema::create('sub__memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->foreignId('member_id')->constrained('sub__members')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('sub__plans')->restrictOnDelete();
            $table->foreignId('renewed_from_id')->nullable()->constrained('sub__memberships')->nullOnDelete();
            $table->unsignedBigInteger('sold_at_branch_id')->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('active'); // active | expired | cancelled
            $table->unsignedSmallInteger('sessions_total')->nullable();
            $table->unsignedSmallInteger('sessions_used')->default(0);
            $table->unsignedSmallInteger('frozen_days')->default(0);
            $table->decimal('price', 12, 2);
            $table->unsignedBigInteger('invoice_id')->nullable(); // Invoicing invoice, by id only
            $table->string('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('sold_by')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'starts_on']);
            $table->index(['client_id', 'status', 'ends_on']);
        });

        Schema::create('sub__freezes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained('sub__memberships')->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedSmallInteger('days');
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['membership_id', 'starts_on']);
        });

        Schema::create('sub__check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('sub__members')->cascadeOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained('sub__memberships')->nullOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->timestamp('checked_in_at');
            $table->string('method', 10)->default('card'); // card | staff
            $table->boolean('allowed');
            $table->string('reason', 30)->nullable();       // why it was refused, or "repeat"
            $table->boolean('used_session')->default(false);
            $table->unsignedBigInteger('checked_by')->nullable();

            $table->index(['member_id', 'checked_in_at']);
            $table->index(['branch_id', 'checked_in_at']);
            $table->foreign('branch_id')->references('id')->on('platform__branches')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['sub__check_ins', 'sub__freezes', 'sub__memberships', 'sub__members', 'sub__plan_branch', 'sub__plans'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
