<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Auth Module — verification_codes, media (shared)
     *
     * verification_codes: Replaces password_reset_tokens + otp_tokens
     *   - Reusable for register, reset_password, phone_verify
     *   - Supports email + SMS channels
     *
     * media: Polymorphic — shared across all modules
     *   - Replaces individual image URL fields
     *   - Uses Laravel morphMany/morphOne
     */
    public function up(): void
    {
        // ── verification_codes ─────────────────────────────────
        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('identifier'); // email or phone
            $table->string('type', 20);
            $table->string('code'); // hashed 6-digit or token
            $table->string('channel', 10)->default('email');
            $table->smallInteger('attempt_count')->default(0);
            $table->smallInteger('max_attempts')->default(5);
            $table->boolean('is_used')->default(false);
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['identifier', 'type', 'is_used']);
        });

        DB::statement("ALTER TABLE verification_codes ADD CONSTRAINT chk_verif_type CHECK (type IN ('register', 'reset_password', 'phone_verify'))");
        DB::statement("ALTER TABLE verification_codes ADD CONSTRAINT chk_verif_channel CHECK (channel IN ('email', 'sms'))");

        // Partial index: only check non-used, non-expired codes
        DB::statement("CREATE INDEX idx_verif_expire ON verification_codes(expires_at)");

        // ── media (polymorphic) ────────────────────────────────
        Schema::create('media', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->string('mediable_type', 50);
            $table->uuid('mediable_id');
            $table->string('collection', 50)->default('default');
            $table->string('file_name');
            $table->string('file_path', 500);
            $table->string('mime_type', 100);
            $table->integer('file_size'); // bytes
            $table->smallInteger('sort_order')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->index(['mediable_type', 'mediable_id']);
            $table->index(['mediable_type', 'mediable_id', 'collection']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
        Schema::dropIfExists('verification_codes');
    }
};
