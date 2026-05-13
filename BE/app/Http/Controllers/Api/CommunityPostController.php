<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StoreCommunityCommentRequest;
use App\Http\Requests\Community\UpsertCommunityPostRequest;
use App\Models\CommunityPost;
use App\Models\CommunityPostComment;
use App\Models\CommunityPostLike;
use App\Models\FavoriteVenue;
use App\Models\VenueCluster;
use App\Support\ApiResponse;
use App\Traits\AuthorizesVenueScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommunityPostController extends Controller
{
    use AuthorizesVenueScope;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = CommunityPost::query()
            ->with(['author', 'venueCluster'])
            ->withExists(['likes as is_liked' => fn ($likeQuery) => $likeQuery->where('user_id', $user->id)])
            ->when($request->filled('author_id'), fn ($q) => $q->where('author_id', $request->string('author_id')->toString()))
            ->when($request->filled('venue_cluster_id'), fn ($q) => $q->where('venue_cluster_id', $request->string('venue_cluster_id')->toString()));

        if ($this->canModerate($user) && $request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        } else {
            $query->where('status', 'published');
        }

        if ($request->boolean('prioritize_favorite_venues')) {
            $favoriteIds = FavoriteVenue::where('user_id', $user->id)->pluck('venue_cluster_id')->all();
            if ($favoriteIds !== []) {
                $placeholders = implode(',', array_fill(0, count($favoriteIds), '?'));
                $query->orderByRaw("CASE WHEN venue_cluster_id IN ({$placeholders}) THEN 0 ELSE 1 END", $favoriteIds);
            }
        }

        $posts = $query->latest()->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched community posts successfully', $posts);
    }

    public function show(Request $request, CommunityPost $post): JsonResponse
    {
        if ($post->status !== 'published' && ! $this->canManagePost($request, $post)) {
            abort(403, 'You cannot access this community post.');
        }

        $post->load(['author', 'venueCluster', 'comments' => fn ($query) => $query->where('status', 'visible')->with('user')->latest()]);
        $post->loadExists(['likes as is_liked' => fn ($query) => $query->where('user_id', $request->user()->id)]);

        return ApiResponse::success('Fetched community post successfully', $post);
    }

    public function store(UpsertCommunityPostRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['author_id'] = $request->user()->id;

        if (! $this->canModerate($request->user())) {
            $data['status'] = 'published';
        } else {
            $data['status'] = $data['status'] ?? 'published';
        }

        if (! empty($data['venue_cluster_id'])) {
            $this->assertCanManageCluster($request, VenueCluster::findOrFail($data['venue_cluster_id']));
        }

        $post = CommunityPost::create($data);

        return ApiResponse::success('Community post created successfully', $post->load(['author', 'venueCluster']), 201);
    }

    public function update(UpsertCommunityPostRequest $request, CommunityPost $post): JsonResponse
    {
        abort_unless($this->canManagePost($request, $post), 403, 'You cannot update this community post.');

        $data = $request->validated();
        if (! $this->canModerate($request->user())) {
            unset($data['status']);
        }

        if (array_key_exists('venue_cluster_id', $data) && $data['venue_cluster_id'] !== null) {
            $this->assertCanManageCluster($request, VenueCluster::findOrFail($data['venue_cluster_id']));
        }

        $post->update($data);

        return ApiResponse::success('Community post updated successfully', $post->fresh(['author', 'venueCluster']));
    }

    public function destroy(Request $request, CommunityPost $post): JsonResponse
    {
        abort_unless($this->canManagePost($request, $post), 403, 'You cannot delete this community post.');

        $post->delete();

        return ApiResponse::success('Community post deleted successfully');
    }

    public function like(Request $request, CommunityPost $post): JsonResponse
    {
        if ($post->status !== 'published') {
            abort(403, 'You cannot like this community post.');
        }

        $post = DB::transaction(function () use ($request, $post) {
            $lockedPost = CommunityPost::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();
            $like = CommunityPostLike::firstOrCreate([
                'post_id' => $lockedPost->id,
                'user_id' => $request->user()->id,
            ]);

            if ($like->wasRecentlyCreated) {
                $lockedPost->increment('like_count');
            }

            return $lockedPost->fresh(['author', 'venueCluster']);
        });

        return ApiResponse::success('Community post liked successfully', $post);
    }

    public function unlike(Request $request, CommunityPost $post): JsonResponse
    {
        $post = DB::transaction(function () use ($request, $post) {
            $lockedPost = CommunityPost::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();
            $deleted = CommunityPostLike::where('post_id', $lockedPost->id)
                ->where('user_id', $request->user()->id)
                ->delete();

            if ($deleted > 0) {
                $lockedPost->update([
                    'like_count' => max(0, $lockedPost->like_count - 1),
                ]);
            }

            return $lockedPost->fresh(['author', 'venueCluster']);
        });

        return ApiResponse::success('Community post unliked successfully', $post);
    }

    public function comments(Request $request, CommunityPost $post): JsonResponse
    {
        if ($post->status !== 'published' && ! $this->canManagePost($request, $post)) {
            abort(403, 'You cannot access comments for this community post.');
        }

        $comments = CommunityPostComment::query()
            ->with(['user', 'replies.user'])
            ->where('post_id', $post->id)
            ->whereNull('parent_id')
            ->where('status', 'visible')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched community comments successfully', $comments);
    }

    public function storeComment(StoreCommunityCommentRequest $request, CommunityPost $post): JsonResponse
    {
        if ($post->status !== 'published') {
            abort(403, 'You cannot comment on this community post.');
        }

        $comment = DB::transaction(function () use ($request, $post) {
            $lockedPost = CommunityPost::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();

            if ($request->filled('parent_id')) {
                $parentExists = CommunityPostComment::where('post_id', $lockedPost->id)
                    ->whereKey($request->validated('parent_id'))
                    ->exists();

                if (! $parentExists) {
                    throw ValidationException::withMessages([
                        'parent_id' => ['Parent comment does not belong to this post.'],
                    ]);
                }
            }

            $comment = CommunityPostComment::create([
                'post_id' => $lockedPost->id,
                'user_id' => $request->user()->id,
                'content' => $request->validated('content'),
                'parent_id' => $request->validated('parent_id'),
                'status' => 'visible',
            ]);

            $lockedPost->increment('comment_count');

            return $comment;
        });

        return ApiResponse::success('Community comment created successfully', $comment->load('user'), 201);
    }

    public function recordView(Request $request, CommunityPost $post): JsonResponse
    {
        if ($post->status !== 'published') {
            abort(403, 'You cannot view this community post.');
        }

        $key = "community_post_viewed:{$post->id}:{$request->user()->id}";
        if (Cache::add($key, true, now()->addMinutes(30))) {
            $post->increment('view_count');
        }

        return ApiResponse::success('Community post view recorded successfully', $post->fresh());
    }

    public function hide(CommunityPost $post): JsonResponse
    {
        $post->update(['status' => 'hidden']);

        return ApiResponse::success('Community post hidden successfully', $post->fresh(['author', 'venueCluster']));
    }

    public function publish(CommunityPost $post): JsonResponse
    {
        $post->update(['status' => 'published']);

        return ApiResponse::success('Community post published successfully', $post->fresh(['author', 'venueCluster']));
    }

    private function canManagePost(Request $request, CommunityPost $post): bool
    {
        return $post->author_id === $request->user()->id || $this->canModerate($request->user());
    }

    private function canModerate($user): bool
    {
        return $user->hasRole('super_admin')
            || $user->hasRole('system_staff')
            || $user->hasPermission('community_post.moderate');
    }
}
