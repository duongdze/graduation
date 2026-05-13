<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feedback\StorePlayerRatingRequest;
use App\Models\PlayerRating;
use App\Models\PlayerPost;
use App\Models\User;
use App\Services\RatingAggregateService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlayerRatingController extends Controller
{
    public function __construct(private readonly RatingAggregateService $ratingAggregateService) {}

    public function index(Request $request): JsonResponse
    {
        $ratings = PlayerRating::query()
            ->with(['rater', 'ratedUser', 'post'])
            ->when($request->filled('rated_user_id'), fn ($query) => $query->where('rated_user_id', $request->string('rated_user_id')->toString()))
            ->when($request->filled('rater_id'), fn ($query) => $query->where('rater_id', $request->string('rater_id')->toString()))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched player ratings successfully', $ratings);
    }

    public function store(StorePlayerRatingRequest $request): JsonResponse
    {
        $rating = DB::transaction(function () use ($request) {
            if ($request->user()->id === $request->validated('rated_user_id')) {
                throw ValidationException::withMessages([
                    'rated_user_id' => ['You cannot rate yourself.'],
                ]);
            }

            $post = PlayerPost::query()
                ->with('participants')
                ->whereKey($request->validated('post_id'))
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertUsersParticipatedInPost($post, $request->user()->id, $request->validated('rated_user_id'));

            $rating = PlayerRating::updateOrCreate(
                [
                    'rater_id' => $request->user()->id,
                    'rated_user_id' => $request->validated('rated_user_id'),
                    'post_id' => $post->id,
                ],
                [
                    'rating' => $request->validated('rating'),
                    'comment' => $request->validated('comment'),
                    'tags' => $request->validated('tags'),
                ]
            );

            $this->ratingAggregateService->syncPlayerRating(User::findOrFail($request->validated('rated_user_id')));

            return $rating;
        });

        return ApiResponse::success('Player rating saved successfully', $rating->load(['rater', 'ratedUser', 'post']), 201);
    }

    private function assertUsersParticipatedInPost(PlayerPost $post, string $raterId, string $ratedUserId): void
    {
        $approvedParticipantIds = $post->participants
            ->where('status', 'approved')
            ->pluck('user_id')
            ->all();

        $raterIsAuthor = $post->author_id === $raterId;
        $ratedIsAuthor = $post->author_id === $ratedUserId;
        $raterApproved = in_array($raterId, $approvedParticipantIds, true);
        $ratedApproved = in_array($ratedUserId, $approvedParticipantIds, true);

        $allowed = ($raterIsAuthor && $ratedApproved)
            || ($ratedIsAuthor && $raterApproved)
            || ($raterApproved && $ratedApproved);

        if (! $allowed) {
            throw ValidationException::withMessages([
                'post_id' => ['Both users must be the post author or approved participants in this player post.'],
            ]);
        }
    }
}
