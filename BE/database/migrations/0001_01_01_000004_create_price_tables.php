<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Price Configuration Module
     *
     * Relationships:
     *   venue_clusters 1:N price_slots
     *   venue_clusters 1:N holiday_prices
     *
     * Price priority when calculating booking price:
     *   1. holiday_prices (check date first)
     *   2. price_slots (match day_of_week + time range)
     *   3. Error — no price configured
     */
    public function up(): void
    {
        // ── price_slots ────────────────────────────────────────
        Schema::create('price_slots', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('cluster_id')->constrained('venue_clusters')->cascadeOnDelete();
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('price', 12, 2);
            $table->jsonb('apply_to_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['cluster_id', 'is_active']);
        });

        DB::statement("ALTER TABLE price_slots ADD CONSTRAINT chk_price_time CHECK (end_time > start_time)");
        DB::statement("ALTER TABLE price_slots ADD CONSTRAINT chk_price_amount CHECK (price >= 0)");

        // ── holiday_prices ─────────────────────────────────────
        Schema::create('holiday_prices', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('cluster_id')->constrained('venue_clusters')->cascadeOnDelete();
            $table->date('holiday_date');
            $table->decimal('price', 12, 2);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['cluster_id', 'holiday_date']);
        });

        DB::statement("ALTER TABLE holiday_prices ADD CONSTRAINT chk_holiday_price CHECK (price >= 0)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holiday_prices');
        Schema::dropIfExists('price_slots');
    }
};
