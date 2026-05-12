<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Partner + Venue Module
     *
     * Relationships:
     *   users 1:N partner_applications (user_id, reviewed_by)
     *   users 1:1 venue_clusters (owner_id) — one owner can have multiple clusters
     *   venue_clusters 1:N venue_courts
     *   court_types 1:N venue_courts
     *   venue_clusters 1:1 booking_configs
     *
     * Media (polymorphic):
     *   partner_applications → collection: license, id_card_front, id_card_back
     *   venue_clusters → collection: cover, gallery
     */
    public function up(): void
    {
        // ── court_types (must exist before venue_courts) ───────
        Schema::create('court_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->integer('player_count');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // ── partner_applications ───────────────────────────────
        Schema::create('partner_applications', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('business_name');
            $table->string('tax_code', 50)->nullable();
            $table->string('status', 20)->default('pending');
            $table->uuid('reviewed_by')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index(['status', 'submitted_at']);

            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement("ALTER TABLE partner_applications ADD CONSTRAINT chk_partner_status CHECK (status IN ('pending', 'approved', 'rejected'))");

        // ── venue_clusters ─────────────────────────────────────
        Schema::create('venue_clusters', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('phone_contact', 15)->nullable();
            $table->text('address');
            $table->string('ward', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->jsonb('amenities')->nullable();
            $table->string('status', 20)->default('pending');
            $table->uuid('approved_by')->nullable();
            $table->text('reject_reason')->nullable();
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->integer('rating_count')->default(0);
            $table->timestamps();

            $table->index('owner_id');
            $table->index('status');
            $table->index('city');

            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement("ALTER TABLE venue_clusters ADD CONSTRAINT chk_venue_status CHECK (status IN ('pending', 'active', 'rejected', 'locked'))");

        // Partial index for active venues sorted by rating
        DB::statement("CREATE INDEX idx_venue_active_rating ON venue_clusters(status, rating_avg DESC)");

        // Standard index for basic lookup (MySQL Spatial needs different syntax, this is a fallback)
        DB::statement("CREATE INDEX idx_venue_geo ON venue_clusters(latitude, longitude)");

        // ── booking_configs (1:1 with venue_clusters) ──────────
        Schema::create('booking_configs', function (Blueprint $table) {
            $table->uuid('cluster_id')->primary();
            $table->integer('min_duration_minutes')->default(60);
            $table->integer('max_duration_minutes')->default(180);
            $table->integer('cancel_before_hours')->default(24);
            $table->integer('refund_percent')->default(100);
            $table->timestamps();

            $table->foreign('cluster_id')->references('id')->on('venue_clusters')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE booking_configs ADD CONSTRAINT chk_config_min CHECK (min_duration_minutes >= 30)");
        DB::statement("ALTER TABLE booking_configs ADD CONSTRAINT chk_config_max CHECK (max_duration_minutes <= 480)");
        DB::statement("ALTER TABLE booking_configs ADD CONSTRAINT chk_config_refund CHECK (refund_percent BETWEEN 0 AND 100)");
        DB::statement("ALTER TABLE booking_configs ADD CONSTRAINT chk_config_min_max CHECK (min_duration_minutes < max_duration_minutes)");

        // ── venue_courts ───────────────────────────────────────
        Schema::create('venue_courts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('cluster_id')->constrained('venue_clusters')->cascadeOnDelete();
            $table->foreignId('court_type_id')->constrained('court_types')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('status', 20)->default('active');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE venue_courts ADD CONSTRAINT chk_court_status CHECK (status IN ('active', 'maintenance'))");
        DB::statement("CREATE INDEX idx_courts_cluster ON venue_courts(cluster_id)");
        DB::statement("CREATE INDEX idx_courts_cluster_status ON venue_courts(cluster_id, status)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venue_courts');
        Schema::dropIfExists('booking_configs');
        Schema::dropIfExists('venue_clusters');
        Schema::dropIfExists('partner_applications');
        Schema::dropIfExists('court_types');
    }
};
