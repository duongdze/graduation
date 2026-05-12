<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Feedback Module — reviews, player_ratings, complaints, reports
     *
     * Relationships:
     *   bookings 1:1 reviews (booking_id UNIQUE)
     *   users N:N player_ratings (rater ↔ rated, per post)
     *   bookings 1:N complaints
     *   reports: polymorphic → User, Review, PlayerPost, PlayerRating
     *
     * Denormalized fields updated by Observers:
     *   reviews → venue_clusters.rating_avg, rating_count
     *   player_ratings → users.player_rating_avg, player_rating_count
     */
    public function up(): void
    {
        // ── reviews (venue/court rating) ───────────────────────
        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('booking_id');
            $table->foreignUuid('customer_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('cluster_id');
            $table->smallInteger('rating');
            $table->text('comment')->nullable();
            $table->text('reply_content')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
            $table->foreign('cluster_id')->references('id')->on('venue_clusters')->cascadeOnDelete();
        });

        DB::statement("CREATE UNIQUE INDEX idx_reviews_booking ON reviews(booking_id)");
        DB::statement("ALTER TABLE reviews ADD CONSTRAINT chk_review_rating CHECK (rating BETWEEN 1 AND 5)");
        DB::statement("CREATE INDEX idx_reviews_cluster ON reviews(cluster_id, is_visible, created_at DESC)");
        DB::statement("CREATE INDEX idx_reviews_customer ON reviews(customer_id)");

        // ── complaints ─────────────────────────────────────────
        Schema::create('complaints', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignUuid('customer_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->string('status', 20)->default('open');
            $table->uuid('resolved_by')->nullable();
            $table->text('resolve_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();

            $table->index('booking_id');
            $table->index('status');
            $table->index('customer_id');
        });

        DB::statement("ALTER TABLE complaints ADD CONSTRAINT chk_complaint_status CHECK (status IN ('open', 'processing', 'resolved', 'closed'))");

        // ── reports (polymorphic moderation) ───────────────────
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reportable_type', 50);
            $table->uuid('reportable_id');
            $table->string('reason', 50);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('action_taken', 20)->nullable();
            $table->text('action_note')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['reportable_type', 'reportable_id']);
            $table->index(['status', 'created_at']);
        });

        DB::statement("CREATE UNIQUE INDEX idx_reports_unique ON reports(reporter_id, reportable_type, reportable_id)");
        DB::statement("ALTER TABLE reports ADD CONSTRAINT chk_report_reason CHECK (reason IN ('spam', 'offensive', 'fake', 'harassment', 'other'))");
        DB::statement("ALTER TABLE reports ADD CONSTRAINT chk_report_status CHECK (status IN ('pending', 'reviewing', 'resolved', 'dismissed'))");
        DB::statement("ALTER TABLE reports ADD CONSTRAINT chk_report_action CHECK (action_taken IS NULL OR action_taken IN ('warning', 'content_hidden', 'content_deleted', 'user_suspended', 'user_banned'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('reviews');
    }
};
