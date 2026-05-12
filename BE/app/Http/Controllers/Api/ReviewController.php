<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feedback\UpsertReviewRequest;
use App\Models\Booking;
use App\Models\Review;
use App\Services\RatingAggregateService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function __construct(private readonly RatingAggregateService $ratingAggregateService) {}

    public function index(Request $request): JsonResponse
    {
        $reviews = Review::query()
            ->with(['customer', 'cluster', 'booking'])
            ->when($request->filled('cluster_id'), fn ($query) => $query->where('cluster_id', $request->string('cluster_id')->toString()))
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->string('customer_id')->toString()))
            ->when($request->filled('is_visible'), fn ($query) => $query->where('is_visible', $request->boolean('is_visible')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched reviews successfully', $reviews);
    }

    public function show(Review $review): JsonResponse
    {
        return ApiResponse::success('Fetched review successfully', $review->load(['customer', 'cluster', 'booking', 'media']));
    }

    public function store(UpsertReviewRequest $request): JsonResponse
    {
        $review = DB::transaction(function () use ($request) {
            $booking = Booking::query()->whereKey($request->validated('booking_id'))->lockForUpdate()->firstOrFail();

            if ($booking->status !== 'completed') {
                throw ValidationException::withMessages([
                    'booking_id' => ['Only completed bookings can be reviewed.'],
                ]);
            }

            if ($booking->customer_id !== $request->user()->id && ! $request->user()->hasPermission('review.moderate')) {
                throw ValidationException::withMessages([
                    'booking_id' => ['You can only review your own booking.'],
                ]);
            }

            $review = Review::create([
                'booking_id' => $booking->id,
                'customer_id' => $request->user()->id,
                'cluster_id' => $booking->cluster_id,
                'rating' => $request->validated('rating'),
                'comment' => $request->validated('comment'),
                'is_visible' => $request->validated('is_visible') ?? true,
            ]);

            $this->ratingAggregateService->syncVenueRating($booking->cluster);

            return $review;
        });

        return ApiResponse::success('Review created successfully', $review->load(['customer', 'cluster']), 201);
    }

    public function update(UpsertReviewRequest $request, Review $review): JsonResponse
    {
        if ($review->customer_id !== $request->user()->id && ! $request->user()->hasPermission('review.moderate')) {
            throw ValidationException::withMessages([
                'review_id' => ['You can only update your own review.'],
            ]);
        }

        $review->update(collect($request->validated())->except(['booking_id'])->all());
        $this->ratingAggregateService->syncVenueRating($review->cluster);

        return ApiResponse::success('Review updated successfully', $review->fresh(['customer', 'cluster']));
    }

    public function destroy(Review $review): JsonResponse
    {
        $cluster = $review->cluster;
        $review->delete();
        $this->ratingAggregateService->syncVenueRating($cluster);

        return ApiResponse::success('Review deleted successfully');
    }
}
