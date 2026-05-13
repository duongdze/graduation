<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\UpsertSystemPolicyRequest;
use App\Models\SystemPolicy;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemPolicyController extends Controller
{
    public function publicIndex(Request $request): JsonResponse
    {
        $policies = SystemPolicy::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->orderBy('type')
            ->orderBy('key')
            ->get();

        return ApiResponse::success('Fetched public system policies successfully', $policies);
    }

    public function index(Request $request): JsonResponse
    {
        $policies = SystemPolicy::query()
            ->with(['creator', 'updater'])
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched system policies successfully', $policies);
    }

    public function show(SystemPolicy $policy): JsonResponse
    {
        return ApiResponse::success('Fetched system policy successfully', $policy->load(['creator', 'updater']));
    }

    public function store(UpsertSystemPolicyRequest $request): JsonResponse
    {
        $policy = SystemPolicy::create(array_merge($request->validated(), [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));

        return ApiResponse::success('System policy created successfully', $policy, 201);
    }

    public function update(UpsertSystemPolicyRequest $request, SystemPolicy $policy): JsonResponse
    {
        $policy->update(array_merge($request->validated(), [
            'updated_by' => $request->user()->id,
        ]));

        return ApiResponse::success('System policy updated successfully', $policy->fresh(['creator', 'updater']));
    }

    public function destroy(SystemPolicy $policy): JsonResponse
    {
        $policy->delete();

        return ApiResponse::success('System policy deleted successfully');
    }
}
