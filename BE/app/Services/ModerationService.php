<?php

namespace App\Services;

use App\Models\ModerationConfig;
use App\Models\PlayerRating;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Models\VenueCluster;
use Illuminate\Support\Facades\DB;

class ModerationService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly AuditLogService $auditLogService
    ) {}

    public static function defaultConfigs(): array
    {
        return [
            'warning_report_count_week' => ['value' => 3, 'value_type' => 'integer', 'description' => 'Weekly user report count that triggers a warning.'],
            'auto_ban_report_count_month' => ['value' => 5, 'value_type' => 'integer', 'description' => 'Monthly user report count that locks the account.'],
            'venue_warning_report_count_week' => ['value' => 3, 'value_type' => 'integer', 'description' => 'Weekly venue report count that warns the owner.'],
            'venue_auto_lock_report_count_month' => ['value' => 5, 'value_type' => 'integer', 'description' => 'Monthly venue report count that locks the venue.'],
            'bad_rating_threshold' => ['value' => 3, 'value_type' => 'integer', 'description' => 'Ratings below this value are considered bad.'],
            'bad_rating_count_month_warning' => ['value' => 5, 'value_type' => 'integer', 'description' => 'Monthly bad rating count that triggers a warning.'],
            'auto_lock_rating_avg_threshold' => ['value' => 2.5, 'value_type' => 'decimal', 'description' => 'Average rating below this value can trigger an auto lock.'],
            'min_rating_count_for_auto_lock' => ['value' => 10, 'value_type' => 'integer', 'description' => 'Minimum rating count required before auto lock by average rating.'],
            'user_auto_ban_report_reason' => ['value' => 'Auto locked because the account exceeded the monthly report threshold.', 'value_type' => 'string', 'description' => 'Reason stored when a user is auto locked by reports.'],
            'venue_auto_lock_report_reason' => ['value' => 'Auto locked because the venue exceeded the monthly report threshold.', 'value_type' => 'string', 'description' => 'Reason stored when a venue is auto locked by reports.'],
            'user_auto_lock_rating_reason' => ['value' => 'Auto locked because the account rating average is below the moderation threshold.', 'value_type' => 'string', 'description' => 'Reason stored when a user is auto locked by bad ratings.'],
            'venue_auto_lock_rating_reason' => ['value' => 'Auto locked because the venue rating average is below the moderation threshold.', 'value_type' => 'string', 'description' => 'Reason stored when a venue is auto locked by bad ratings.'],
        ];
    }

    public function getConfig(string $key): mixed
    {
        $config = ModerationConfig::find($key);
        if ($config) {
            return $config->castedValue();
        }

        $default = self::defaultConfigs()[$key] ?? null;
        if ($default === null) {
            return null;
        }

        return match ($default['value_type']) {
            'integer' => (int) $default['value'],
            'decimal' => (float) $default['value'],
            'boolean' => (bool) $default['value'],
            default => (string) $default['value'],
        };
    }

    public function seedDefaultConfigs(): void
    {
        foreach (self::defaultConfigs() as $key => $config) {
            ModerationConfig::firstOrCreate(
                ['key' => $key],
                [
                    'value' => (string) $config['value'],
                    'value_type' => $config['value_type'],
                    'description' => $config['description'],
                ]
            );
        }
    }

    public function lockUser(User $user, string $reason, ?User $actor = null, string $source = 'manual'): User
    {
        return DB::transaction(function () use ($user, $reason, $actor, $source) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $oldValues = $locked->only(['status', 'lock_reason', 'locked_at', 'locked_by']);

            $locked->update([
                'status' => 'locked',
                'lock_reason' => $reason,
                'locked_at' => now(),
                'locked_by' => $actor?->id,
            ]);
            $locked->tokens()->delete();

            $this->notificationService->createForUser(
                $locked->id,
                'account_locked',
                'Account locked',
                $reason,
                'User',
                $locked->id,
                ['source' => $source]
            );
            $this->notifyAdmins(
                'moderation_lock',
                'Account locked by moderation',
                $reason,
                'User',
                $locked->id,
                ['source' => $source]
            );

            $this->auditLogService->log(
                $actor?->id,
                $source === 'auto' ? 'user.auto_locked' : 'user.locked',
                'User',
                $locked->id,
                $oldValues,
                $locked->fresh()->only(['status', 'lock_reason', 'locked_at', 'locked_by']),
                'moderation'
            );

            return $locked->fresh();
        });
    }

    public function unlockUser(User $user, ?User $actor = null): User
    {
        return DB::transaction(function () use ($user, $actor) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $oldValues = $locked->only(['status', 'lock_reason', 'locked_at', 'locked_by']);

            $locked->update([
                'status' => 'active',
                'lock_reason' => null,
                'locked_at' => null,
                'locked_by' => null,
            ]);

            $this->auditLogService->log(
                $actor?->id,
                'user.unlocked',
                'User',
                $locked->id,
                $oldValues,
                $locked->fresh()->only(['status', 'lock_reason', 'locked_at', 'locked_by']),
                'moderation'
            );

            return $locked->fresh();
        });
    }

    public function lockVenue(VenueCluster $venueCluster, string $reason, ?User $actor = null, string $source = 'manual'): VenueCluster
    {
        return DB::transaction(function () use ($venueCluster, $reason, $actor, $source) {
            $locked = VenueCluster::query()->whereKey($venueCluster->id)->lockForUpdate()->firstOrFail();
            $oldValues = $locked->only(['status', 'lock_reason', 'locked_at', 'locked_by']);

            $locked->update([
                'status' => 'locked',
                'lock_reason' => $reason,
                'locked_at' => now(),
                'locked_by' => $actor?->id,
            ]);

            $this->notificationService->createForUser(
                $locked->owner_id,
                'venue_locked',
                'Venue locked',
                $reason,
                'VenueCluster',
                $locked->id,
                ['source' => $source]
            );
            $this->notifyAdmins(
                'moderation_lock',
                'Venue locked by moderation',
                $reason,
                'VenueCluster',
                $locked->id,
                ['source' => $source]
            );

            $this->auditLogService->log(
                $actor?->id,
                $source === 'auto' ? 'venue.auto_locked' : 'venue.locked',
                'VenueCluster',
                $locked->id,
                $oldValues,
                $locked->fresh()->only(['status', 'lock_reason', 'locked_at', 'locked_by']),
                'moderation'
            );

            return $locked->fresh();
        });
    }

    public function unlockVenue(VenueCluster $venueCluster, ?User $actor = null): VenueCluster
    {
        return DB::transaction(function () use ($venueCluster, $actor) {
            $locked = VenueCluster::query()->whereKey($venueCluster->id)->lockForUpdate()->firstOrFail();
            $oldValues = $locked->only(['status', 'lock_reason', 'locked_at', 'locked_by']);

            $locked->update([
                'status' => 'active',
                'lock_reason' => null,
                'locked_at' => null,
                'locked_by' => null,
            ]);

            $this->auditLogService->log(
                $actor?->id,
                'venue.unlocked',
                'VenueCluster',
                $locked->id,
                $oldValues,
                $locked->fresh()->only(['status', 'lock_reason', 'locked_at', 'locked_by']),
                'moderation'
            );

            return $locked->fresh();
        });
    }

    public function evaluate(): array
    {
        $summary = [
            'user_report_warnings' => 0,
            'user_report_locks' => 0,
            'venue_report_warnings' => 0,
            'venue_report_locks' => 0,
            'user_rating_warnings' => 0,
            'user_rating_locks' => 0,
            'venue_rating_warnings' => 0,
            'venue_rating_locks' => 0,
        ];

        $this->evaluateReportThresholds($summary);
        $this->evaluateRatingThresholds($summary);

        return $summary;
    }

    private function evaluateReportThresholds(array &$summary): void
    {
        $userWeekThreshold = (int) $this->getConfig('warning_report_count_week');
        $userMonthThreshold = (int) $this->getConfig('auto_ban_report_count_month');
        $venueWeekThreshold = (int) $this->getConfig('venue_warning_report_count_week');
        $venueMonthThreshold = (int) $this->getConfig('venue_auto_lock_report_count_month');

        foreach ($this->reportedEntityIds(User::class, now()->subDays(7), $userWeekThreshold) as $userId) {
            $user = User::whereKey($userId)->where('status', '!=', 'locked')->first();
            if (! $user) {
                continue;
            }

            $this->notificationService->createForUser(
                $user->id,
                'moderation_warning',
                'Account warning',
                'Your account has received multiple reports and is under moderation review.',
                'User',
                $user->id
            );
            $this->notifyAdmins('moderation_warning', 'Account report warning', 'A user account exceeded the weekly report warning threshold.', 'User', $user->id);
            $summary['user_report_warnings']++;
        }

        foreach ($this->reportedEntityIds(User::class, now()->subDays(30), $userMonthThreshold) as $userId) {
            $user = User::whereKey($userId)->where('status', '!=', 'locked')->first();
            if (! $user) {
                continue;
            }

            $this->lockUser($user, (string) $this->getConfig('user_auto_ban_report_reason'), null, 'auto');
            $summary['user_report_locks']++;
        }

        foreach ($this->reportedEntityIds(VenueCluster::class, now()->subDays(7), $venueWeekThreshold) as $clusterId) {
            $cluster = VenueCluster::whereKey($clusterId)->where('status', '!=', 'locked')->first();
            if (! $cluster) {
                continue;
            }

            $this->notificationService->createForUser(
                $cluster->owner_id,
                'moderation_warning',
                'Venue warning',
                'Your venue has received multiple reports and is under moderation review.',
                'VenueCluster',
                $cluster->id
            );
            $this->notifyAdmins('moderation_warning', 'Venue report warning', 'A venue exceeded the weekly report warning threshold.', 'VenueCluster', $cluster->id);
            $summary['venue_report_warnings']++;
        }

        foreach ($this->reportedEntityIds(VenueCluster::class, now()->subDays(30), $venueMonthThreshold) as $clusterId) {
            $cluster = VenueCluster::whereKey($clusterId)->where('status', '!=', 'locked')->first();
            if (! $cluster) {
                continue;
            }

            $this->lockVenue($cluster, (string) $this->getConfig('venue_auto_lock_report_reason'), null, 'auto');
            $summary['venue_report_locks']++;
        }
    }

    private function evaluateRatingThresholds(array &$summary): void
    {
        $badRatingThreshold = (int) $this->getConfig('bad_rating_threshold');
        $badRatingWarningCount = (int) $this->getConfig('bad_rating_count_month_warning');
        $autoRatingAvg = (float) $this->getConfig('auto_lock_rating_avg_threshold');
        $minRatingCount = (int) $this->getConfig('min_rating_count_for_auto_lock');

        $badUserRatingIds = PlayerRating::query()
            ->select('rated_user_id')
            ->where('rating', '<', $badRatingThreshold)
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('rated_user_id')
            ->havingRaw('COUNT(*) >= ?', [$badRatingWarningCount])
            ->pluck('rated_user_id');

        foreach ($badUserRatingIds as $userId) {
            $user = User::whereKey($userId)->where('status', '!=', 'locked')->first();
            if (! $user) {
                continue;
            }

            $this->notificationService->createForUser(
                $user->id,
                'moderation_warning',
                'Low rating warning',
                'Your account has received multiple low ratings.',
                'User',
                $user->id
            );
            $this->notifyAdmins('moderation_warning', 'Account rating warning', 'A user account exceeded the monthly low-rating warning threshold.', 'User', $user->id);
            $summary['user_rating_warnings']++;
        }

        User::where('status', '!=', 'locked')
            ->where('player_rating_avg', '<', $autoRatingAvg)
            ->where('player_rating_count', '>=', $minRatingCount)
            ->each(function (User $user) use (&$summary) {
                $this->lockUser($user, (string) $this->getConfig('user_auto_lock_rating_reason'), null, 'auto');
                $summary['user_rating_locks']++;
            });

        $badVenueRatingIds = Review::query()
            ->select('cluster_id')
            ->where('is_visible', true)
            ->where('rating', '<', $badRatingThreshold)
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('cluster_id')
            ->havingRaw('COUNT(*) >= ?', [$badRatingWarningCount])
            ->pluck('cluster_id');

        foreach ($badVenueRatingIds as $clusterId) {
            $cluster = VenueCluster::whereKey($clusterId)->where('status', '!=', 'locked')->first();
            if (! $cluster) {
                continue;
            }

            $this->notificationService->createForUser(
                $cluster->owner_id,
                'moderation_warning',
                'Low venue rating warning',
                'Your venue has received multiple low ratings.',
                'VenueCluster',
                $cluster->id
            );
            $this->notifyAdmins('moderation_warning', 'Venue rating warning', 'A venue exceeded the monthly low-rating warning threshold.', 'VenueCluster', $cluster->id);
            $summary['venue_rating_warnings']++;
        }

        VenueCluster::where('status', '!=', 'locked')
            ->where('rating_avg', '<', $autoRatingAvg)
            ->where('rating_count', '>=', $minRatingCount)
            ->each(function (VenueCluster $cluster) use (&$summary) {
                $this->lockVenue($cluster, (string) $this->getConfig('venue_auto_lock_rating_reason'), null, 'auto');
                $summary['venue_rating_locks']++;
            });
    }

    private function reportedEntityIds(string $modelClass, mixed $since, int $threshold)
    {
        return Report::query()
            ->select('reportable_id')
            ->where('reportable_type', $modelClass)
            ->where('status', '!=', 'dismissed')
            ->where('created_at', '>=', $since)
            ->groupBy('reportable_id')
            ->havingRaw('COUNT(*) >= ?', [$threshold])
            ->pluck('reportable_id');
    }

    private function notifyAdmins(
        string $type,
        string $title,
        string $body,
        ?string $referenceType = null,
        ?string $referenceId = null,
        array $data = []
    ): void {
        $adminIds = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['super_admin', 'system_staff']))
            ->pluck('id')
            ->all();

        if ($adminIds === []) {
            return;
        }

        $this->notificationService->createForUsers($adminIds, $type, $title, $body, $referenceType, $referenceId, $data);
    }
}
