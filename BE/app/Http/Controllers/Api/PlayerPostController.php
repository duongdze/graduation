<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\UpsertPlayerPostRequest;
use App\Models\PlayerPost;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerPostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $posts = PlayerPost::query()
            ->with(['author', 'courtType', 'venueCluster'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('sport_type'), fn ($query) => $query->where('sport_type', $request->string('sport_type')->toString()))
            ->when($request->filled('play_date'), fn ($query) => $query->whereDate('play_date', $request->date('play_date')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched player posts successfully', $posts);
    }

    public function store(UpsertPlayerPostRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['author_id'] = $request->user()->id;
        $data['current_players'] = 1;
        $data['gender_preference'] = $data['gender_preference'] ?? 'any';
        $data['is_auto_approve'] = $data['is_auto_approve'] ?? true;
        $data['status'] = $data['status'] ?? 'open';

        $post = PlayerPost::create($data);

        return ApiResponse::success('Player post created successfully', $post->load(['author', 'courtType', 'venueCluster']), 201);
    }

    public function show(PlayerPost $playerPost): JsonResponse
    {
        return ApiResponse::success('Fetched player post successfully', $playerPost->load(['author', 'courtType', 'venueCluster', 'participants.user', 'media']));
    }

    public function update(UpsertPlayerPostRequest $request, PlayerPost $playerPost): JsonResponse
    {
        $this->assertCanManagePost($request, $playerPost);

        $data = collect($request->validated())->except(['current_players', 'author_id'])->all();
        $playerPost->update($data);

        return ApiResponse::success('Player post updated successfully', $playerPost->fresh(['author', 'courtType', 'venueCluster']));
    }

    public function destroy(Request $request, PlayerPost $playerPost): JsonResponse
    {
        $this->assertCanManagePost($request, $playerPost);

        $playerPost->delete();

        return ApiResponse::success('Player post deleted successfully');
    }

    private function assertCanManagePost(Request $request, PlayerPost $playerPost): void
    {
        abort_unless(
            $playerPost->author_id === $request->user()->id || $request->user()->hasRole('super_admin'),
            403,
            'You cannot manage this player post.'
        );
    }
}
