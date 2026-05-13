<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\UpsertBannerRequest;
use App\Models\Banner;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function publicIndex(Request $request): JsonResponse
    {
        $banners = Banner::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->when($request->filled('position'), fn ($query) => $query->where('position', $request->string('position')->toString()))
            ->orderBy('sort_order')
            ->latest()
            ->get();

        return ApiResponse::success('Fetched public banners successfully', $banners);
    }

    public function index(Request $request): JsonResponse
    {
        $banners = Banner::query()
            ->with(['creator', 'updater'])
            ->when($request->filled('position'), fn ($query) => $query->where('position', $request->string('position')->toString()))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('sort_order')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched banners successfully', $banners);
    }

    public function store(UpsertBannerRequest $request): JsonResponse
    {
        $banner = Banner::create(array_merge($request->validated(), [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));

        return ApiResponse::success('Banner created successfully', $banner, 201);
    }

    public function update(UpsertBannerRequest $request, Banner $banner): JsonResponse
    {
        $banner->update(array_merge($request->validated(), [
            'updated_by' => $request->user()->id,
        ]));

        return ApiResponse::success('Banner updated successfully', $banner->fresh(['creator', 'updater']));
    }

    public function destroy(Banner $banner): JsonResponse
    {
        $banner->delete();

        return ApiResponse::success('Banner deleted successfully');
    }

    public function toggle(Request $request, Banner $banner): JsonResponse
    {
        $request->validate([
            'is_active' => ['nullable', 'boolean'],
        ]);

        $banner->update([
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : ! $banner->is_active,
            'updated_by' => $request->user()->id,
        ]);

        return ApiResponse::success('Banner toggled successfully', $banner->fresh());
    }
}
