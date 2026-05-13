<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Chat Module — Lightweight messaging
     *
     * Relationships:
     *   users 1:N conversations (created_by)
     *   conversations 1:N messages
     *   conversations N:N users via conversation_participants
     *
     * API: Polling-based (GET every 15-30s), not WebSocket
     *
     * Denormalized:
     *   conversations.last_message_at — updated by Observer on new message
     *   Unread count = messages WHERE created_at > participant.last_read_at
     */
    public function up(): void
    {
        // ── conversations ──────────────────────────────────────
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->string('type', 20)->default('direct');
            $table->string('reference_type', 50)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('title')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['last_message_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        DB::statement("ALTER TABLE conversations ADD CONSTRAINT chk_conv_type CHECK (type IN ('direct', 'post'))");

        // ── conversation_participants ──────────────────────────
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('joined_at')->useCurrent();

            $table->unique(['conversation_id', 'user_id']);
            $table->index('user_id');
        });

        // ── messages ───────────────────────────────────────────
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->boolean('is_system')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
