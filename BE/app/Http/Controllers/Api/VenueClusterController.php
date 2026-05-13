<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Moderation\LockResourceRequest;
use App\Http\Requests\Venue\UpsertVenueClusterRequest;
use App\Models\BookingConfig;
use App\Models\PriceSlot;
use App\Models\VenueCluster;
use App\Models\VenueViewEvent;
use App\Services\AuditLogService;
use App\Services\ModerationService;
use App\Services\NotificationService;
use App\Support\ApiResponse;
use App\Traits\AuthorizesVenueScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VenueClusterController extends Controller
{
    use AuthorizesVenueScope;

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly AuditLogService $auditLogService,
        private readonly ModerationService $moderationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isVenueScopedUser = $user->hasRole('venue_owner') || $this->venueScopeIds($user) !== [];

        $query = $this->scopeVenueClustersForUser(VenueCluster::query(), $request->user())
            ->with(['owner', 'approver'])
            ->withExists(['favorites as is_favorited' => fn ($q) => $q->where('user_id', $request->user()->id)])
            ->when($request->boolean('favorite_only'), fn ($q) => $q->whereHas('favorites', fn ($fq) => $fq->where('user_id', $request->user()->id)))
            ->when($request->filled('city'), fn ($q) => $q->where('city', $request->string('city')->toString()))
            ->when($request->filled('district'), fn ($q) => $q->where('district', $request->string('district')->toString()))
            ->when($request->filled('owner_id'), fn ($q) => $q->where('owner_id', $request->string('owner_id')->toString()))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->toString();
                $q->where(function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('court_type_id'), fn ($q) => $q->whereHas('courts', fn ($cq) => $cq->where('court_type_id', $request->integer('court_type_id'))))
            ->when($request->filled('min_price'), fn ($q) => $q->whereHas('priceSlots', fn ($pq) => $pq->where('price', '>=', $request->input('min_price'))))
            ->when($request->filled('max_price'), fn ($q) => $q->whereHas('priceSlots', fn ($pq) => $pq->where('price', '<=', $request->input('max_price'))));

        if ($this->isPlatformAdmin($user) || $isVenueScopedUser) {
            $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()));
        } else {
            $query->where('status', 'active');
        }

        // Geo-distance sorting (Haversine)
        if ($request->filled('lat') && $request->filled('lng')) {
            $lat = (float) $request->input('lat');
            $lng = (float) $request->input('lng');
            $radiusKm = (float) $request->input('radius_km', 50);

            $query->selectRaw('*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance_km', [$lat, $lng, $lat])
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->having('distance_km', '<=', $radiusKm)
                ->orderBy('distance_km');
        } else {
            $query->latest();
        }

        $clusters = $query->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched venue clusters successfully', $clusters);
    }

    public function store(UpsertVenueClusterRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (! $this->isPlatformAdmin($request->user())) {
            $data['owner_id'] = $request->user()->id;
            $data['status'] = 'pending';
        } else {
            $data['owner_id'] = $data['owner_id'] ?? $request->user()->id;
        }

        $cluster = VenueCluster::create($data);

        return ApiResponse::success('Venue cluster created successfully', $cluster->load('owner'), 201);
    }

    public function show(VenueCluster $venueCluster): JsonResponse
    {
        $user = request()->user();
        $canSeePrivateVenue = $this->isPlatformAdmin($user)
            || $venueCluster->owner_id === $user->id
            || in_array($venueCluster->id, $this->venueScopeIds($user), true);

        abort_unless($venueCluster->status === 'active' || $canSeePrivateVenue, 404);

        return ApiResponse::success('Fetched venue cluster successfully', $venueCluster->load(['owner', 'courts.courtType', 'bookingConfig', 'priceSlots', 'media']));
    }

    public function update(UpsertVenueClusterRequest $request, VenueCluster $venueCluster): JsonResponse
    {
        $this->assertCanManageCluster($request, $venueCluster);
        $oldValues = $venueCluster->only(array_keys($request->validated()));

        $venueCluster->update($request->validated());

        $this->auditLogService->log(
            $request->user()->id,
            'venue_cluster.updated',
            'VenueCluster',
            $venueCluster->id,
            $oldValues,
            $venueCluster->fresh()->only(array_keys($request->validated())),
            'venue',
            $request
        );

        return ApiResponse::success('Venue cluster updated successfully', $venueCluster->fresh(['owner', 'courts']));
    }

    public function destroy(Request $request, VenueCluster $venueCluster): JsonResponse
    {
        $this->assertCanManageCluster($request, $venueCluster);
        $venueCluster->delete();

        return ApiResponse::success('Venue cluster deleted successfully');
    }

    public function approve(Request $request, VenueCluster $venueCluster): JsonResponse
    {
        DB::transaction(function () use ($request, $venueCluster) {
            $venueCluster->update([
                'status' => 'active',
                'approved_by' => $request->user()->id,
                'reject_reason' => null,
            ]);

            // Auto-create default booking config if not exists
            BookingConfig::firstOrCreate(
                ['cluster_id' => $venueCluster->id],
                [
                    'min_duration_minutes' => 60,
                    'max_duration_minutes' => 180,
                    'cancel_before_hours' => 24,
                    'refund_percent' => 100,
                ]
            );

            foreach ($this->defaultPriceSlots($venueCluster->id) as $slot) {
                PriceSlot::firstOrCreate(
                    [
                        'cluster_id' => $slot['cluster_id'],
                        'start_time' => $slot['start_time'],
                        'end_time' => $slot['end_time'],
                    ],
                    $slot
                );
            }

            // Notify venue owner
            $this->notificationService->createForUser(
                $venueCluster->owner_id,
                'venue_approved',
                'Cụm sân đã được duyệt',
                "Cụm sân \"{$venueCluster->name}\" đã được phê duyệt. Hãy bắt đầu cấu hình sân con và giá.",
                'VenueCluster',
                $venueCluster->id
            );

            $this->auditLogService->log(
                $request->user()->id,
                'venue_cluster.approved',
                'VenueCluster',
                $venueCluster->id,
                null,
                ['status' => 'active'],
                'venue',
                $request
            );
        });

        return ApiResponse::success('Venue cluster approved successfully', $venueCluster->fresh(['approver', 'bookingConfig']));
    }

    public function reject(Request $request, VenueCluster $venueCluster): JsonResponse
    {
        $request->validate(['reject_reason' => ['required', 'string']]);

        $venueCluster->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'reject_reason' => $request->string('reject_reason')->toString(),
        ]);

        $this->notificationService->createForUser(
            $venueCluster->owner_id,
            'venue_rejected',
            'Venue was rejected',
            $request->string('reject_reason')->toString(),
            'VenueCluster',
            $venueCluster->id
        );

        $this->auditLogService->log(
            $request->user()->id,
            'venue_cluster.rejected',
            'VenueCluster',
            $venueCluster->id,
            null,
            ['status' => 'rejected', 'reject_reason' => $request->string('reject_reason')->toString()],
            'venue',
            $request
        );

        return ApiResponse::success('Venue cluster rejected successfully', $venueCluster->fresh('approver'));
    }

    public function lock(LockResourceRequest $request, VenueCluster $venueCluster): JsonResponse
    {
        $venueCluster = $this->moderationService->lockVenue($venueCluster, $request->validated('reason'), $request->user());

        return ApiResponse::success('Venue cluster locked successfully', $venueCluster->fresh(['owner', 'locker']));
    }

    public function unlock(Request $request, VenueCluster $venueCluster): JsonResponse
    {
        $venueCluster = $this->moderationService->unlockVenue($venueCluster, $request->user());

        return ApiResponse::success('Venue cluster unlocked successfully', $venueCluster->fresh(['owner', 'locker']));
    }

    public function recordView(Request $request, VenueCluster $venueCluster): JsonResponse
    {
        $event = VenueViewEvent::create([
            'cluster_id' => $venueCluster->id,
            'user_id' => $request->user()?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent() ? substr($request->userAgent(), 0, 500) : null,
        ]);

        return ApiResponse::success('Venue view recorded successfully', $event, 201);
    }

    private function defaultPriceSlots(string $clusterId): array
    {
        return [
            [
                'cluster_id' => $clusterId,
                'start_time' => '06:00:00',
                'end_time' => '17:00:00',
                'price' => 100000,
                'apply_to_days' => [0, 1, 2, 3, 4, 5, 6],
                'is_active' => true,
            ],
            [
                'cluster_id' => $clusterId,
                'start_time' => '17:00:00',
                'end_time' => '22:00:00',
                'price' => 150000,
                'apply_to_days' => [0, 1, 2, 3, 4, 5, 6],
                'is_active' => true,
            ],
        ];
    }
}
