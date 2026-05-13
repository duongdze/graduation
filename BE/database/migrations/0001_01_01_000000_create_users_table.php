<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL does not need extensions for UUID

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('phone', 15)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password');
            $table->string('avatar_url', 500)->nullable();
            $table->string('status', 20)->default('pending_verify');
            $table->text('bio')->nullable();
            $table->jsonb('preferred_sports')->nullable();
            $table->string('preferred_position', 50)->nullable();
            $table->decimal('player_rating_avg', 3, 2)->default(0);
            $table->integer('player_rating_count')->default(0);
            $table->rememberToken();
            $table->timestamps();

            $table->index('status');
        });

        // Unique index for phone
        DB::statement("CREATE UNIQUE INDEX idx_users_phone ON users(phone)");

        // Add CHECK constraint for status
        DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_status CHECK (status IN ('pending_verify', 'active', 'locked'))");

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index()->constrained('users')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
