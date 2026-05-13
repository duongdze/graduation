<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\StoreMediaRequest;
use App\Models\Media;
use App\Services\MediaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class MediaController extends Controller
{
    public function __construct(private readonly MediaService $mediaService) {}

    public function upload(StoreMediaRequest $request): JsonResponse
    {
        $media = $this->mediaService->store($request->validated(), $request->file('file'));

        return ApiResponse::success('Media uploaded successfully', $media, 201);
    }

    public function destroy(Media $media): JsonResponse
    {
        $this->mediaService->delete($media);

        return ApiResponse::success('Media deleted successfully');
    }
}
