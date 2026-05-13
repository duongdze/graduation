<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\UpsertSystemPostRequest;
use App\Models\SystemPost;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemPostController extends Controller
{
    public function publicIndex(Request $request): JsonResponse
    {
        $posts = SystemPost::query()
            ->with('author')
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->latest('published_at')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched public system posts successfully', $posts);
    }

    public function index(Request $request): JsonResponse
    {
        $posts = SystemPost::query()
            ->with('author')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched system posts successfully', $posts);
    }

    public function show(SystemPost $post): JsonResponse
    {
        return ApiResponse::success('Fetched system post successfully', $post->load('author'));
    }

    public function store(UpsertSystemPostRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['author_id'] = $request->user()->id;

        if (($data['status'] ?? null) === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $post = SystemPost::create($data);

        return ApiResponse::success('System post created successfully', $post->load('author'), 201);
    }

    public function update(UpsertSystemPostRequest $request, SystemPost $post): JsonResponse
    {
        $data = $request->validated();

        if (($data['status'] ?? $post->status) === 'published' && empty($data['published_at']) && $post->published_at === null) {
            $data['published_at'] = now();
        }

        $post->update($data);

        return ApiResponse::success('System post updated successfully', $post->fresh('author'));
    }

    public function destroy(SystemPost $post): JsonResponse
    {
        $post->delete();

        return ApiResponse::success('System post deleted successfully');
    }

    public function publish(SystemPost $post): JsonResponse
    {
        $post->update([
            'status' => 'published',
            'published_at' => $post->published_at ?? now(),
        ]);

        return ApiResponse::success('System post published successfully', $post->fresh('author'));
    }
}
