<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Complaint;
use App\Models\Payment;
use App\Models\PlayerPost;
use App\Models\PriceSlot;
use App\Models\Refund;
use App\Models\Review;
use App\Models\SlotLock;
use App\Models\User;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Models\VenueFeeLedger;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VenueOwnerDashboardService
{
    public function overview(User $owner, array $filters): array
    {
        $from = Carbon::parse($filters['from'] ?? now()->startOfMonth())->startOfDay();
        $to = Carbon::parse($filters['to'] ?? now())->endOfDay();
        $limit = (int) ($filters['limit'] ?? 10);
        $clusters = $this->resolveClusters($owner, $filters['cluster_id'] ?? null);
        $clusterIds = $clusters->pluck('id');

        $totalCourts = VenueCourt::whereIn('cluster_id', $clusterIds)->count();
        $activeCourts = VenueCourt::whereIn('cluster_id', $clusterIds)->where('status', 'active')->count();
        $maintenanceCourts = VenueCourt::whereIn('cluster_id', $clusterIds)->where('status', 'maintenance')->count();

        $bookingsInRange = $this->bookingQuery($clusterIds, $from, $to);
        $activeBookingsInRange = (clone $bookingsInRange)->whereNotIn('status', ['cancelled', 'expired']);
        $paidBookingStatuses = ['paid', 'checked_in', 'completed'];

        $grossRevenue = $this->paymentQuery($clusterIds, $from, $to)->sum('payments.amount');
        $platformFee = VenueFeeLedger::whereIn('cluster_id', $clusterIds)
            ->whereBetween('created_at', [$from, $to])
            ->sum('fee_amount');

        $bookedMinutes = (clone $activeBookingsInRange)->sum('duration_minutes');
        $availableMinutes = max($this->availableMinutes($clusterIds, $from, $to), 1);

        return [
            'filters' => [
                'cluster_id' => $filters['cluster_id'] ?? null,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'limit' => $limit,
            ],
            'venues' => $this->venueSummary($clusters),
            'kpis' => [
                'gross_revenue' => (float) $grossRevenue,
                'platform_fee' => (float) $platformFee,
                'net_revenue' => max((float) $grossRevenue - (float) $platformFee, 0),
                'total_bookings' => (clone $bookingsInRange)->count(),
                'paid_bookings' => (clone $bookingsInRange)->whereIn('status', $paidBookingStatuses)->count(),
                'pending_payment_bookings' => (clone $bookingsInRange)->where('status', 'pending_payment')->count(),
                'completed_bookings' => (clone $bookingsInRange)->where('status', 'completed')->count(),
                'cancelled_bookings' => (clone $bookingsInRange)->where('status', 'cancelled')->count(),
                'today_bookings' => $this->todayBookingQuery($clusterIds)->count(),
                'upcoming_bookings' => Booking::whereIn('cluster_id', $clusterIds)
                    ->whereDate('booking_date', '>=', today())
                    ->whereIn('status', ['pending_payment', 'paid', 'checked_in'])
                    ->count(),
                'occupancy_rate' => round($bookedMinutes / $availableMinutes * 100, 2),
                'total_courts' => $totalCourts,
                'active_courts' => $activeCourts,
                'maintenance_courts' => $maintenanceCourts,
                'average_rating' => round((float) $clusters->avg('rating_avg'), 2),
                'rating_count' => (int) $clusters->sum('rating_count'),
                'open_complaints' => $this->complaintQuery($clusterIds)->whereIn('status', ['open', 'processing'])->count(),
                'pending_refunds' => $this->refundQuery($clusterIds)->whereIn('refunds.status', ['pending', 'processing'])->count(),
                'active_player_posts' => PlayerPost::whereIn('venue_cluster_id', $clusterIds)->where('status', 'open')->count(),
                'active_slot_locks' => SlotLock::whereHas('court', fn ($query) => $query->whereIn('cluster_id', $clusterIds))
                    ->where('expires_at', '>', now())
                    ->count(),
            ],
            'charts' => [
                'revenue_by_day' => $this->revenueByDay($clusterIds, $from, $to),
                'bookings_by_day' => $this->bookingsByDay($clusterIds, $from, $to),
                'booking_status_breakdown' => $this->bookingStatusBreakdown($clusterIds, $from, $to),
                'peak_hours' => $this->peakHours($clusterIds, $from, $to, $limit),
            ],
            'top_courts' => $this->topCourts($clusterIds, $from, $to, $limit),
            'today_schedule' => $this->todaySchedule($clusterIds, $limit),
            'recent_reviews' => $this->recentReviews($clusterIds, $limit),
            'tasks' => $this->tasks($clusterIds),
            'available_actions' => [
                'manage_bookings',
                'check_in_today',
                'manage_pricing',
                'manage_courts',
                'reply_reviews',
                'handle_complaints',
            ],
        ];
    }

    private function resolveClusters(User $owner, ?string $clusterId): Collection
    {
        $query = VenueCluster::query()
            ->where('owner_id', $owner->id)
            ->when($clusterId, fn ($clusterQuery) => $clusterQuery->whereKey($clusterId))
            ->withCount('courts')
            ->orderBy('name');

        $clusters = $query->get();

        if ($clusterId !== null && $clusters->isEmpty()) {
            throw new AuthorizationException('You cannot view dashboard for this venue.');
        }

        return $clusters;
    }

    private function venueSummary(Collection $clusters): array
    {
        return [
            'total' => $clusters->count(),
            'active' => $clusters->where('status', 'active')->count(),
            'pending' => $clusters->where('status', 'pending')->count(),
            'rejected' => $clusters->where('status', 'rejected')->count(),
            'locked' => $clusters->where('status', 'locked')->count(),
            'items' => $clusters->map(fn (VenueCluster $cluster) => [
                'id' => $cluster->id,
                'name' => $cluster->name,
                'status' => $cluster->status,
                'rating_avg' => (float) $cluster->rating_avg,
                'rating_count' => (int) $cluster->rating_count,
                'courts_count' => (int) $cluster->courts_count,
            ])->values(),
        ];
    }

    private function bookingQuery(Collection $clusterIds, Carbon $from, Carbon $to)
    {
        return Booking::whereIn('cluster_id', $clusterIds)
            ->whereBetween('booking_date', [$from->toDateString(), $to->toDateString()]);
    }

    private function paymentQuery(Collection $clusterIds, Carbon $from, Carbon $to)
    {
        return Payment::query()
            ->join('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->whereIn('bookings.cluster_id', $clusterIds)
            ->where('payments.status', 'success')
            ->whereBetween('payments.paid_at', [$from, $to]);
    }

    private function complaintQuery(Collection $clusterIds)
    {
        return Complaint::whereHas('booking', fn ($query) => $query->whereIn('cluster_id', $clusterIds));
    }

    private function refundQuery(Collection $clusterIds)
    {
        return Refund::query()
            ->join('bookings', 'refunds.booking_id', '=', 'bookings.id')
            ->whereIn('bookings.cluster_id', $clusterIds);
    }

    private function todayBookingQuery(Collection $clusterIds)
    {
        return Booking::whereIn('cluster_id', $clusterIds)->whereDate('booking_date', today());
    }

    private function revenueByDay(Collection $clusterIds, Carbon $from, Carbon $to): Collection
    {
        return $this->paymentQuery($clusterIds, $from, $to)
            ->select(
                DB::raw('DATE(payments.paid_at) as period'),
                DB::raw('COALESCE(SUM(payments.amount), 0) as revenue'),
                DB::raw('COUNT(payments.id) as payment_count')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();
    }

    private function bookingsByDay(Collection $clusterIds, Carbon $from, Carbon $to): Collection
    {
        return $this->bookingQuery($clusterIds, $from, $to)
            ->select(
                'booking_date as period',
                DB::raw('COUNT(*) as booking_count'),
                DB::raw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count")
            )
            ->groupBy('booking_date')
            ->orderBy('booking_date')
            ->get();
    }

    private function bookingStatusBreakdown(Collection $clusterIds, Carbon $from, Carbon $to): Collection
    {
        return $this->bookingQuery($clusterIds, $from, $to)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->orderBy('status')
            ->get();
    }

    private function peakHours(Collection $clusterIds, Carbon $from, Carbon $to, int $limit): Collection
    {
        return $this->bookingQuery($clusterIds, $from, $to)
            ->whereNotIn('status', ['cancelled', 'expired'])
            ->select('start_time', DB::raw('COUNT(*) as booking_count'))
            ->groupBy('start_time')
            ->orderByDesc('booking_count')
            ->orderBy('start_time')
            ->limit($limit)
            ->get();
    }

    private function topCourts(Collection $clusterIds, Carbon $from, Carbon $to, int $limit): Collection
    {
        return Booking::query()
            ->join('venue_courts', 'bookings.court_id', '=', 'venue_courts.id')
            ->join('venue_clusters', 'bookings.cluster_id', '=', 'venue_clusters.id')
            ->leftJoin('payments', function ($join) {
                $join->on('payments.booking_id', '=', 'bookings.id')
                    ->where('payments.status', '=', 'success');
            })
            ->whereIn('bookings.cluster_id', $clusterIds)
            ->whereBetween('bookings.booking_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('bookings.status', ['cancelled', 'expired'])
            ->select(
                'venue_courts.id',
                'venue_courts.name',
                'venue_courts.cluster_id',
                'venue_clusters.name as cluster_name',
                DB::raw('COUNT(DISTINCT bookings.id) as booking_count'),
                DB::raw('COALESCE(SUM(payments.amount), 0) as revenue')
            )
            ->groupBy('venue_courts.id', 'venue_courts.name', 'venue_courts.cluster_id', 'venue_clusters.name')
            ->orderByDesc('booking_count')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();
    }

    private function todaySchedule(Collection $clusterIds, int $limit): Collection
    {
        return $this->todayBookingQuery($clusterIds)
            ->with(['court:id,name,cluster_id', 'customer:id,full_name,email,phone'])
            ->orderBy('start_time')
            ->limit($limit)
            ->get();
    }

    private function recentReviews(Collection $clusterIds, int $limit): Collection
    {
        return Review::whereIn('cluster_id', $clusterIds)
            ->with(['customer:id,full_name,email', 'cluster:id,name', 'booking:id,booking_code,court_id'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    private function tasks(Collection $clusterIds): array
    {
        return [
            'pending_payment_bookings' => Booking::whereIn('cluster_id', $clusterIds)->where('status', 'pending_payment')->count(),
            'today_checkins' => $this->todayBookingQuery($clusterIds)->where('status', 'paid')->count(),
            'open_complaints' => $this->complaintQuery($clusterIds)->whereIn('status', ['open', 'processing'])->count(),
            'pending_refunds' => $this->refundQuery($clusterIds)->whereIn('refunds.status', ['pending', 'processing'])->count(),
            'maintenance_courts' => VenueCourt::whereIn('cluster_id', $clusterIds)->where('status', 'maintenance')->count(),
            'unreplied_reviews' => Review::whereIn('cluster_id', $clusterIds)->whereNull('reply_content')->count(),
        ];
    }

    private function availableMinutes(Collection $clusterIds, Carbon $from, Carbon $to): int
    {
        $activeCourtsByCluster = VenueCourt::whereIn('cluster_id', $clusterIds)
            ->where('status', 'active')
            ->select('cluster_id', DB::raw('COUNT(*) as court_count'))
            ->groupBy('cluster_id')
            ->pluck('court_count', 'cluster_id');

        $priceSlots = PriceSlot::whereIn('cluster_id', $clusterIds)
            ->where('is_active', true)
            ->get()
            ->groupBy('cluster_id');

        $total = 0;
        foreach (CarbonPeriod::create($from->toDateString(), $to->toDateString()) as $date) {
            $dayOfWeek = $date->dayOfWeek;
            foreach ($clusterIds as $clusterId) {
                $courtCount = (int) ($activeCourtsByCluster[$clusterId] ?? 0);
                if ($courtCount <= 0) {
                    continue;
                }

                $minutesForDay = $priceSlots->get($clusterId, collect())
                    ->filter(fn (PriceSlot $slot) => $slot->apply_to_days === null || in_array($dayOfWeek, $slot->apply_to_days, true))
                    ->sum(fn (PriceSlot $slot) => Carbon::parse($slot->start_time)->diffInMinutes(Carbon::parse($slot->end_time)));

                $total += $courtCount * (int) $minutesForDay;
            }
        }

        if ($total <= 0) {
            return VenueCourt::whereIn('cluster_id', $clusterIds)->where('status', 'active')->count()
                * 14
                * 60
                * ($from->diffInDays($to) + 1);
        }

        return $total;
    }
}
