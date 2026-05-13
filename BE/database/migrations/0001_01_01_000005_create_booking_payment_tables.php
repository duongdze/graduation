<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Booking + Payment + Finance Module (CRITICAL)
     *
     * Transaction-critical tables:
     *   - bookings + slot_locks: SERIALIZABLE + SELECT FOR UPDATE
     *   - payments: Webhook idempotency via gateway_txn_id UNIQUE
     *   - venue_fee_ledgers: Created on booking completion
     *
     * Booking creation flow:
     *   BEGIN → SELECT FOR UPDATE bookings → check slot_locks
     *   → INSERT booking → INSERT slot_lock → SET Redis lock → COMMIT
     *
     * Relationships:
     *   venue_courts 1:N bookings
     *   venue_courts 1:N slot_locks
     *   bookings 1:N payments
     *   bookings 1:N refunds
     *   bookings 1:1 venue_fee_ledgers (on completion)
     */
    public function up(): void
    {
        // ── bookings ───────────────────────────────────────────
        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->string('booking_code', 20)->unique();
            $table->uuid('customer_id')->nullable();
            $table->uuid('court_id');
            $table->uuid('cluster_id');
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('duration_minutes');
            $table->decimal('base_price', 12, 2);
            $table->decimal('total_price', 12, 2);
            $table->string('source', 10)->default('online');
            $table->string('status', 20)->default('pending_payment');
            $table->text('cancel_reason')->nullable();
            $table->uuid('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('walk_in_name')->nullable();
            $table->string('walk_in_phone', 15)->nullable();
            $table->text('note')->nullable();
            $table->uuid('created_by');
            $table->timestamps();

            // Foreign keys
            $table->foreign('customer_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('court_id')->references('id')->on('venue_courts')->restrictOnDelete();
            $table->foreign('cluster_id')->references('id')->on('venue_clusters')->restrictOnDelete();
            $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();

            // Critical indexes for availability check & calendar
            $table->index(['court_id', 'booking_date', 'status']);
            $table->index(['cluster_id', 'booking_date']);
            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE bookings ADD CONSTRAINT chk_booking_time CHECK (end_time > start_time)");
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT chk_booking_source CHECK (source IN ('online', 'counter'))");
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT chk_booking_status CHECK (status IN ('pending_payment', 'paid', 'checked_in', 'completed', 'cancelled', 'expired'))");

        // Customer booking history — partial index excludes walk-in
        DB::statement("CREATE INDEX idx_bookings_customer ON bookings(customer_id, created_at DESC)");

        // Pending bookings for expiry cleanup job
        DB::statement("CREATE INDEX idx_bookings_expire ON bookings(status, created_at)");

        // ── slot_locks ─────────────────────────────────────────
        Schema::create('slot_locks', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('court_id')->constrained('venue_courts')->cascadeOnDelete();
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('locked_by'); // user_id or session_id
            $table->uuid('booking_id')->nullable();
            $table->string('lock_type', 10)->default('auto');
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->nullable();

            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();

            $table->index(['court_id', 'booking_date', 'start_time', 'end_time']);
        });

        DB::statement("ALTER TABLE slot_locks ADD CONSTRAINT chk_lock_type CHECK (lock_type IN ('auto', 'manual'))");

        // Partial index for cleanup job — only auto locks
        DB::statement("CREATE INDEX idx_locks_cleanup ON slot_locks(expires_at)");
        DB::statement("CREATE INDEX idx_locks_booking ON slot_locks(booking_id)");

        // ── payments ───────────────────────────────────────────
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 20);
            $table->string('gateway_txn_id')->nullable();
            $table->jsonb('gateway_response')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('booking_id');
            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payment_amount CHECK (amount > 0)");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payment_method CHECK (method IN ('vnpay', 'momo', 'cash'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payment_status CHECK (status IN ('pending', 'success', 'failed', 'refunded'))");

        // Idempotency key — UNIQUE but only for non-null (cash has no gateway_txn_id)
        DB::statement("CREATE UNIQUE INDEX idx_payments_gateway ON payments(gateway_txn_id)");

        // ── refunds ────────────────────────────────────────────
        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->uuid('payment_id');
            $table->decimal('amount', 12, 2);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->uuid('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('payment_id')->references('id')->on('payments')->restrictOnDelete();
            $table->foreign('processed_by')->references('id')->on('users')->nullOnDelete();

            $table->index('booking_id');
            $table->index('status');
        });

        DB::statement("ALTER TABLE refunds ADD CONSTRAINT chk_refund_amount CHECK (amount > 0)");
        DB::statement("ALTER TABLE refunds ADD CONSTRAINT chk_refund_status CHECK (status IN ('pending', 'processing', 'completed'))");

        // ── platform_fee_configs ───────────────────────────────
        Schema::create('platform_fee_configs', function (Blueprint $table) {
            $table->id();
            $table->decimal('fee_percent', 5, 2);
            $table->decimal('max_fee_percent', 5, 2)->default(30.00);
            $table->timestamp('effective_from');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        // ── venue_fee_ledgers ──────────────────────────────────
        Schema::create('venue_fee_ledgers', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('booking_id');
            $table->uuid('cluster_id');
            $table->decimal('booking_total', 12, 2);
            $table->decimal('fee_percent', 5, 2);
            $table->decimal('fee_amount', 12, 2);
            $table->string('status', 20)->default('pending');
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
            $table->foreign('cluster_id')->references('id')->on('venue_clusters')->restrictOnDelete();

            $table->index(['cluster_id', 'status']);
        });

        DB::statement("CREATE UNIQUE INDEX idx_ledger_booking ON venue_fee_ledgers(booking_id)");
        DB::statement("ALTER TABLE venue_fee_ledgers ADD CONSTRAINT chk_ledger_status CHECK (status IN ('pending', 'reconciled'))");
        DB::statement("CREATE INDEX idx_ledger_reconcile ON venue_fee_ledgers(cluster_id, created_at)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venue_fee_ledgers');
        Schema::dropIfExists('platform_fee_configs');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('slot_locks');
        Schema::dropIfExists('bookings');
    }
};
