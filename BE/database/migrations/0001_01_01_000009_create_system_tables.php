<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * System Module — notifications, audit_logs
     *
     * notifications: Polymorphic reference for navigable click-through
     * audit_logs: Append-only, never UPDATE/DELETE. Whitelist-based actions.
     */
    public function up(): void
    {
        // ── notifications ──────────────────────────────────────
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->jsonb('data')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->index(['reference_type', 'reference_id']);
            $table->timestamp('created_at')->nullable();
        });

        // DESC index for "latest unread notifications" query
        DB::statement("CREATE INDEX idx_notif_user ON notifications(user_id, is_read, created_at DESC)");

        // ── audit_logs (append-only) ───────────────────────────
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('actor_id')->nullable();
            $table->string('action', 100);
            $table->string('entity_type', 50);
            $table->uuid('entity_id');
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('context', 50)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();

            $table->index('actor_id');
            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
    }
};
