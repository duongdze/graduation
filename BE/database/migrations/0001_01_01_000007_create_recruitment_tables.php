<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Recruitment Module — player_posts, player_post_participants, player_ratings
     *
     * Relationships:
     *   users 1:N player_posts (author)
     *   player_posts N:N users via player_post_participants
     *   player_posts → venue_clusters (optional link)
     *   player_posts → bookings (optional link)
     *   users N:N player_ratings (rater ↔ rated, per post context)
     *
     * Denormalized:
     *   player_posts.current_players — updated by Observer on participant change
     *   users.player_rating_avg — updated by Observer on rating change
     */
    public function up(): void
    {
        // ── player_posts ───────────────────────────────────────
        Schema::create('player_posts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('sport_type', 50);
            $table->unsignedBigInteger('court_type_id')->nullable();
            $table->uuid('venue_cluster_id')->nullable();
            $table->uuid('booking_id')->nullable();
            $table->date('play_date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->string('location_name')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->smallInteger('needed_players');
            $table->smallInteger('max_players');
            $table->smallInteger('current_players')->default(1);
            $table->string('skill_level', 20)->nullable();
            $table->string('gender_preference', 10)->default('any');
            $table->decimal('cost_per_player', 12, 2)->nullable();
            $table->boolean('is_auto_approve')->default(true);
            $table->string('status', 20)->default('open');
            $table->timestamps();

            $table->foreign('court_type_id')->references('id')->on('court_types')->nullOnDelete();
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->nullOnDelete();
            $table->foreign('booking_id')->references('id')->on('bookings')->nullOnDelete();

            $table->index('author_id');
            $table->index(['sport_type', 'status', 'play_date']);
        });

        DB::statement("ALTER TABLE player_posts ADD CONSTRAINT chk_post_players CHECK (needed_players >= 1)");
        DB::statement("ALTER TABLE player_posts ADD CONSTRAINT chk_post_max CHECK (max_players >= needed_players)");
        DB::statement("ALTER TABLE player_posts ADD CONSTRAINT chk_post_skill CHECK (skill_level IS NULL OR skill_level IN ('beginner', 'intermediate', 'advanced', 'any'))");
        DB::statement("ALTER TABLE player_posts ADD CONSTRAINT chk_post_gender CHECK (gender_preference IN ('male', 'female', 'any'))");
        DB::statement("ALTER TABLE player_posts ADD CONSTRAINT chk_post_status CHECK (status IN ('open', 'full', 'closed', 'cancelled'))");

        DB::statement("CREATE INDEX idx_posts_open ON player_posts(play_date, status)");
        DB::statement("CREATE INDEX idx_posts_venue ON player_posts(venue_cluster_id)");

        // ── player_post_participants ───────────────────────────
        Schema::create('player_post_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('post_id')->constrained('player_posts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('message')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['post_id', 'user_id']);
            $table->index(['post_id', 'status']);
            $table->index('user_id');
        });

        DB::statement("ALTER TABLE player_post_participants ADD CONSTRAINT chk_participant_status CHECK (status IN ('pending', 'approved', 'rejected', 'cancelled'))");

        // ── player_ratings ─────────────────────────────────────
        Schema::create('player_ratings', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('rater_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('rated_user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('post_id')->nullable();
            $table->smallInteger('rating');
            $table->text('comment')->nullable();
            $table->jsonb('tags')->nullable();
            $table->timestamps();

            $table->foreign('post_id')->references('id')->on('player_posts')->nullOnDelete();

            $table->index(['rated_user_id', 'created_at']);
            $table->index('rater_id');
        });

        DB::statement("ALTER TABLE player_ratings ADD CONSTRAINT chk_prating_value CHECK (rating BETWEEN 1 AND 5)");
        DB::statement("ALTER TABLE player_ratings ADD CONSTRAINT chk_prating_self CHECK (rater_id != rated_user_id)");

        // Partial unique indexes — fix NULL behavior in PostgreSQL
        DB::statement("CREATE UNIQUE INDEX idx_pratings_with_post ON player_ratings(rater_id, rated_user_id, post_id)");
        DB::statement("CREATE UNIQUE INDEX idx_pratings_no_post ON player_ratings(rater_id, rated_user_id)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_ratings');
        Schema::dropIfExists('player_post_participants');
        Schema::dropIfExists('player_posts');
    }
};
