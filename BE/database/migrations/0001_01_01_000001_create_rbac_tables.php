<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * RBAC Module — roles, permissions, role_permissions
     *
     * Relationships:
     *   roles 1:N role_permissions
     *   permissions 1:N role_permissions
     *   role_permissions = junction (roles ↔ permissions)
     */
    public function up(): void
    {
        // ── roles ──────────────────────────────────────────────
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('display_name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        // ── permissions ────────────────────────────────────────
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->string('group_name', 50);
            $table->timestamp('created_at')->nullable();
        });

        // ── role_permissions (junction) ────────────────────────
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        // ── user_roles ─────────────────────────────────────────
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('scope_type', 20)->default('system');
            $table->uuid('scope_id')->nullable();
            $table->uuid('granted_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('user_id');

            // FK for granted_by
            $table->foreign('granted_by')->references('id')->on('users')->nullOnDelete();
        });

        // Composite unique (MySQL treats multiple NULLs as unique, which is standard)
        Schema::table('user_roles', function (Blueprint $table) {
            $table->unique(['user_id', 'role_id', 'scope_type', 'scope_id'], 'idx_user_roles_unique');
        });

        // CHECK constraint for scope_type
        DB::statement("ALTER TABLE user_roles ADD CONSTRAINT chk_user_roles_scope CHECK (scope_type IN ('system', 'venue'))");

        // ── user_permission_revokes (deny-only override) ───────
        Schema::create('user_permission_revokes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->string('scope_type', 20)->default('system');
            $table->uuid('scope_id')->nullable();
            $table->uuid('revoked_by')->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('user_id');

            $table->foreign('revoked_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('user_permission_revokes', function (Blueprint $table) {
            $table->unique(['user_id', 'permission_id', 'scope_type', 'scope_id'], 'idx_revokes_unique');
        });

        DB::statement("ALTER TABLE user_permission_revokes ADD CONSTRAINT chk_revokes_scope CHECK (scope_type IN ('system', 'venue'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_permission_revokes');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
