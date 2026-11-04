<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Packages and add-ons Baydaq sells (built by Baydaq admins), and the plan each client is on.
     * A plan is the package plus add-ons plus any custom overrides; applying it writes the
     * per-module subscriptions the suite already checks, and adds limits and feature flags.
     */
    public function up(): void
    {
        Schema::create('platform__packages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->decimal('price_monthly', 12, 2)->default(0);
            $table->decimal('price_yearly', 12, 2)->default(0);
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->json('modules');       // module values: hr, pos, ...
            $table->json('features');      // feature keys: hr.tasks, suite.board, ...
            $table->json('mobile');        // mobile app flags: mobile.attendance, ...
            $table->json('limits');        // organizations, companies, branches, employees, users, registers; null = unlimited
            $table->boolean('is_public')->default(false); // offered on the sign-up page
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('platform__addons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->decimal('price_monthly', 12, 2)->default(0);
            $table->json('modules');
            $table->json('features');
            $table->json('mobile');
            $table->json('limits');        // added on top of the package, e.g. {"employees": 25}
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('platform__client_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('platform__packages')->restrictOnDelete();
            $table->json('addons');        // [{addon_id, quantity}]
            $table->json('overrides');     // {limits: {...}, features: {key: bool}, mobile: {key: bool}}
            $table->string('status', 12);  // trial | active | suspended
            $table->string('billing_cycle', 10)->default('monthly');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform__client_plans');
        Schema::dropIfExists('platform__addons');
        Schema::dropIfExists('platform__packages');
    }
};
