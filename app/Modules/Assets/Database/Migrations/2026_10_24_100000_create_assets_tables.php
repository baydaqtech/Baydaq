<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fixed assets: one category list per owner, each asset tagged (AST-00001) and kept at a branch,
     * its custody history (HR employees, by id only), maintenance jobs, and stock-takes that compare
     * what is expected at a branch with what was scanned.
     */
    public function up(): void
    {
        Schema::create('ast__categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            $table->unsignedSmallInteger('useful_life_months')->default(60);
            $table->boolean('depreciates')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ast__assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('category_id')->constrained('ast__categories')->restrictOnDelete();
            $table->string('tag', 20);
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('serial')->nullable();
            $table->string('model')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('cost', 14, 2)->default(0);
            $table->decimal('salvage_value', 14, 2)->default(0);
            $table->unsignedSmallInteger('useful_life_months')->nullable(); // falls back to the category
            $table->string('status', 20)->default('available'); // available | assigned | maintenance | lost | disposed
            $table->date('disposed_on')->nullable();
            $table->decimal('disposal_amount', 14, 2)->nullable();
            $table->string('disposal_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'tag']);
            $table->index(['client_id', 'branch_id', 'status']);
            $table->foreign(['company_id', 'branch_id'])->references(['company_id', 'id'])->on('platform__branches')->restrictOnDelete();
        });

        Schema::create('ast__custodies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('ast__assets')->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id'); // HR employee, by id only
            $table->date('assigned_on');
            $table->date('returned_on')->nullable();
            $table->string('condition_out')->nullable();
            $table->string('condition_in')->nullable();
            $table->string('note')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->unsignedBigInteger('returned_by')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'returned_on']);
            $table->index(['asset_id', 'returned_on']);
        });

        Schema::create('ast__maintenance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('ast__assets')->cascadeOnDelete();
            $table->string('kind', 20)->default('repair'); // repair | preventive
            $table->string('description');
            $table->unsignedBigInteger('reported_by_employee_id')->nullable();
            $table->date('opened_on');
            $table->date('closed_on')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('vendor')->nullable();
            $table->string('resolution')->nullable();
            $table->unsignedBigInteger('opened_by')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'closed_on']);
        });

        Schema::create('ast__audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');
            $table->date('started_on');
            $table->timestamp('closed_at')->nullable();
            $table->string('note')->nullable();
            $table->unsignedBigInteger('started_by')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamps();

            $table->foreign(['company_id', 'branch_id'])->references(['company_id', 'id'])->on('platform__branches')->restrictOnDelete();
        });

        Schema::create('ast__audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('ast__audits')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('ast__assets')->cascadeOnDelete();
            $table->string('status', 20)->default('expected'); // expected | found | missing | unexpected
            $table->timestamp('scanned_at')->nullable();

            $table->unique(['audit_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        foreach (['ast__audit_items', 'ast__audits', 'ast__maintenance', 'ast__custodies', 'ast__assets', 'ast__categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
