<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Venue\UpsertCourtTypeRequest;
use App\Models\CourtType;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourtTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $courtTypes = CourtType::query()
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 50));

        return ApiResponse::paginated('Fetched court types successfully', $courtTypes);
    }

    public function show(CourtType $courtType): JsonResponse
    {
        return ApiResponse::success('Fetched court type successfully', $courtType);
    }

    public function store(UpsertCourtTypeRequest $request): JsonResponse
    {
        $courtType = CourtType::create($request->validated());

        return ApiResponse::success('Court type created successfully', $courtType, 201);
    }

    public function update(UpsertCourtTypeRequest $request, CourtType $courtType): JsonResponse
    {
        $courtType->update($request->validated());

        return ApiResponse::success('Court type updated successfully', $courtType->fresh());
    }

    public function destroy(CourtType $courtType): JsonResponse
    {
        $courtType->delete();

        return ApiResponse::success('Court type deleted successfully');
    }
}
