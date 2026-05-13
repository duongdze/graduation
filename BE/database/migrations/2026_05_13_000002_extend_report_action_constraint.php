<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropReportActionConstraint();

        DB::statement("ALTER TABLE reports ADD CONSTRAINT chk_report_action CHECK (action_taken IS NULL OR action_taken IN ('warning', 'content_hidden', 'content_deleted', 'user_suspended', 'user_banned', 'account_locked', 'venue_warned', 'venue_locked'))");
    }

    public function down(): void
    {
        $this->dropReportActionConstraint();

        DB::statement("ALTER TABLE reports ADD CONSTRAINT chk_report_action CHECK (action_taken IS NULL OR action_taken IN ('warning', 'content_hidden', 'content_deleted', 'user_suspended', 'user_banned'))");
    }

    private function dropReportActionConstraint(): void
    {
        try {
            DB::statement('ALTER TABLE reports DROP CHECK chk_report_action');
        } catch (Throwable) {
            // Older databases or manually altered schemas may not have the check.
        }
    }
};
