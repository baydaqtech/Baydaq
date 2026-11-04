<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tenant-defined roles, their permission keys (one row each), and who holds which role
     * at which point of the tree. A user may hold many assignments.
     */
    public function up(): void
    {
        Schema::create('platform__access_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            // Built-in roles (owner) carry a key and are locked; tenant roles have no key.
            $table->string('key', 40)->nullable();
            $table->string('name_ar');
            $table->string('name_en');
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->unique(['client_id', 'key']);
        });

        Schema::create('platform__access_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_role_id')->constrained('platform__access_roles')->cascadeOnDelete();
            $table->string('permission', 100);

            $table->unique(['access_role_id', 'permission'], 'platform__role_permissions_unique');
        });

        Schema::create('platform__access_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('access_role_id')->constrained('platform__access_roles')->cascadeOnDelete();
            $table->string('level', 20);
            $table->foreignId('organization_id')->nullable()->constrained('platform__organizations')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('platform__companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('platform__branches')->cascadeOnDelete();
            // NULLs never collide in a unique index, so the scope is folded into one key
            // ("company:12"). The model fills it; a generated column would forbid the cascades above.
            $table->string('scope_key', 40);
            $table->timestamps();

            $table->unique(['user_id', 'access_role_id', 'scope_key'], 'platform__assignments_scope_unique');
            $table->index(['client_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform__access_assignments');
        Schema::dropIfExists('platform__access_role_permissions');
        Schema::dropIfExists('platform__access_roles');
    }
};
