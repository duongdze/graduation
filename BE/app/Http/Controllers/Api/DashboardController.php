<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\VenueOwnerDashboardRequest;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\PartnerApplication;
use App\Models\Payment;
use App\Models\Report;
use App\Models\User;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Models\VenueViewEvent;
use App\Services\VenueOwnerDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(private readonly VenueOwnerDashboardService $venueOwnerDashboardService) {}

    public function adminOverview(Request $request): JsonResponse
    {
        $data = [
            'total_users' => User::count(),
            'total_venues' => VenueCluster::where('status', 'active')->count(),
            'total_courts' => VenueCourt::count(),
            'total_bookings' => Booking::count(),
            'total_revenue' => (float) Payment::where('status', 'success')->sum('amount'),
            'pending_partner_applications' => PartnerApplication::where('status', 'pending')->count(),
            'pending_reports' => Report::where('status', 'pending')->count(),
            'pending_complaints' => Complaint::whereIn('status', ['open', 'processing'])->count(),
        ];

        return ApiResponse::success('Fetched admin overview successfully', $data);
    }

    public function venueOwnerOverview(VenueOwnerDashboardRequest $request): JsonResponse
    {
        $data = $this->venueOwnerDashboardService->overview($request->user(), $request->validated());

        return ApiResponse::success('Fetched venue owner dashboard successfully', $data);
    }

    public function revenue(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'group_by' => ['sometimes', 'in:day,month'],
            'cluster_id' => ['sometimes', 'uuid', 'exists:venue_clusters,id'],
        ]);

        $groupBy = $request->input('group_by', 'day');
        $dateFormat = $groupBy === 'month' ? '%Y-%m' : '%Y-%m-%d';

        $query = Payment::where('status', 'success')
            ->whereBetween('paid_at', [$request->input('from'), $request->input('to').' 23:59:59']);

        if ($request->filled('cluster_id')) {
            $query->whereHas('booking', fn ($q) => $q->where('cluster_id', $request->input('cluster_id')));
        }

        $data = $query
            ->select(DB::raw("DATE_FORMAT(paid_at, '{$dateFormat}') as period"), DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return ApiResponse::success('Fetched revenue data successfully', $data);
    }

    public function peakHours(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'cluster_id' => ['sometimes', 'uuid', 'exists:venue_clusters,id'],
        ]);

        $query = Booking::whereNotIn('status', ['cancelled', 'expired']);

        if ($request->filled('from')) {
            $query->where('booking_date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('booking_date', '<=', $request->input('to'));
        }
        if ($request->filled('cluster_id')) {
            $query->where('cluster_id', $request->input('cluster_id'));
        }

        $data = $query
            ->select(
                DB::raw('DAYOFWEEK(booking_date) as day_of_week'),
                'start_time',
                DB::raw('COUNT(*) as booking_count')
            )
            ->groupBy('day_of_week', 'start_time')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return ApiResponse::success('Fetched peak hours successfully', $data);
    }

    public function conversionRate(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'cluster_id' => ['sometimes', 'uuid'],
        ]);

        $query = Booking::query();
        $viewQuery = VenueViewEvent::query();

        if ($request->filled('from')) {
            $query->where('booking_date', '>=', $request->input('from'));
            $viewQuery->where('viewed_at', '>=', $request->date('from')->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('booking_date', '<=', $request->input('to'));
            $viewQuery->where('viewed_at', '<=', $request->date('to')->endOfDay());
        }
        if ($request->filled('cluster_id')) {
            $query->where('cluster_id', $request->input('cluster_id'));
            $viewQuery->where('cluster_id', $request->input('cluster_id'));
        }

        $views = $viewQuery->count();
        $total = $query->count();
        $completed = (clone $query)->whereIn('status', ['paid', 'checked_in', 'completed'])->count();

        return ApiResponse::success('Fetched conversion rate successfully', [
            'venue_views' => $views,
            'total_bookings' => $total,
            'completed_bookings' => $completed,
            'booking_conversion_rate' => $total > 0 ? round($completed / $total * 100, 1) : 0,
            'view_to_paid_booking_rate' => $views > 0 ? round($completed / $views * 100, 2) : 0,
        ]);
    }

    public function venueDensity(Request $request): JsonResponse
    {
        $request->validate([
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
        ]);

        $data = VenueCluster::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($request->filled('city'), fn ($query) => $query->where('city', $request->input('city')))
            ->when($request->filled('district'), fn ($query) => $query->where('district', $request->input('district')))
            ->select(
                'city',
                'district',
                DB::raw('COUNT(*) as venue_count'),
                DB::raw('SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_count'),
                DB::raw('AVG(latitude) as latitude'),
                DB::raw('AVG(longitude) as longitude')
            )
            ->groupBy('city', 'district')
            ->orderByDesc('venue_count')
            ->get();

        return ApiResponse::success('Fetched venue density successfully', $data);
    }
}
