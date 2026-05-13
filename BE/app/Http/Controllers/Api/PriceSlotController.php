<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\UpsertPriceSlotRequest;
use App\Models\PriceSlot;
use App\Models\VenueCluster;
use App\Support\ApiResponse;
use App\Traits\AuthorizesVenueScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PriceSlotController extends Controller
{
    use AuthorizesVenueScope;

    public function index(Request $request): JsonResponse
    {
        $clusterIds = null;
        if ($request->filled('cluster_id') && ($request->user()->hasRole('venue_owner') || $this->venueScopeIds($request->user()) !== []) && ! $this->isPlatformAdmin($request->user())) {
            $cluster = VenueCluster::findOrFail($request->string('cluster_id')->toString());
            $this->assertCanManageCluster($request, $cluster);
            $clusterIds = [$cluster->id];
        } elseif ($request->user()->hasRole('venue_owner') && ! $this->isPlatformAdmin($request->user())) {
            $clusterIds = VenueCluster::where('owner_id', $request->user()->id)->pluck('id')->all();
        }

        $slots = PriceSlot::query()
            ->with('cluster')
            ->when($clusterIds !== null, fn ($query) => $query->whereIn('cluster_id', $clusterIds))
            ->when($request->filled('cluster_id'), fn ($query) => $query->where('cluster_id', $request->string('cluster_id')->toString()))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('start_time')
            ->paginate($request->integer('per_page', 50));

        return ApiResponse::paginated('Fetched price slots successfully', $slots);
    }

    public function show(PriceSlot $priceSlot): JsonResponse
    {
        return ApiResponse::success('Fetched price slot successfully', $priceSlot->load('cluster'));
    }

    public function store(UpsertPriceSlotRequest $request): JsonResponse
    {
        $data = $request->validated();
        $cluster = VenueCluster::findOrFail($data['cluster_id']);
        $this->assertCanManageCluster($request, $cluster);

        $slot = DB::transaction(function () use ($data) {
            $this->assertNoPriceOverlap($data);

            return PriceSlot::create($data);
        });

        return ApiResponse::success('Price slot created successfully', $slot->load('cluster'), 201);
    }

    public function update(UpsertPriceSlotRequest $request, PriceSlot $priceSlot): JsonResponse
    {
        $priceSlot->loadMissing('cluster');
        $this->assertCanManageCluster($request, $priceSlot->cluster);

        $data = array_merge($priceSlot->only([
            'cluster_id',
            'start_time',
            'end_time',
            'price',
            'apply_to_days',
            'is_active',
        ]), $request->validated());

        if (isset($data['cluster_id']) && $data['cluster_id'] !== $priceSlot->cluster_id) {
            $this->assertCanManageCluster($request, VenueCluster::findOrFail($data['cluster_id']));
        }

        DB::transaction(function () use ($priceSlot, $data) {
            $this->assertNoPriceOverlap($data, $priceSlot->id);
            $priceSlot->update($data);
        });

        return ApiResponse::success('Price slot updated successfully', $priceSlot->fresh('cluster'));
    }

    public function destroy(Request $request, PriceSlot $priceSlot): JsonResponse
    {
        $priceSlot->loadMissing('cluster');
        $this->assertCanManageCluster($request, $priceSlot->cluster);

        $priceSlot->delete();

        return ApiResponse::success('Price slot deleted successfully');
    }

    private function assertNoPriceOverlap(array $data, ?string $ignoreId = null): void
    {
        if (($data['is_active'] ?? true) === false) {
            return;
        }

        $days = $this->normalizeDays($data['apply_to_days'] ?? null);

        $overlapping = PriceSlot::query()
            ->where('cluster_id', $data['cluster_id'])
            ->where('is_active', true)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->lockForUpdate()
            ->get()
            ->first(function (PriceSlot $slot) use ($days) {
                return array_intersect($days, $this->normalizeDays($slot->apply_to_days)) !== [];
            });

        if ($overlapping) {
            throw ValidationException::withMessages([
                'start_time' => ['Price slot overlaps an existing active slot for the same day(s).'],
            ]);
        }
    }

    private function normalizeDays(?array $days): array
    {
        if ($days === null || $days === []) {
            return [0, 1, 2, 3, 4, 5, 6];
        }

        return array_values(array_unique(array_map('intval', $days)));
    }
}
