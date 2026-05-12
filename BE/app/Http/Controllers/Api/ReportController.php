<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feedback\ReviewReportRequest;
use App\Http\Requests\Feedback\StoreReportRequest;
use App\Models\PlayerPost;
use App\Models\PlayerRating;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    private const MODEL_ALIASES = [
        'user' => User::class,
        'review' => Review::class,
        'player_post' => PlayerPost::class,
        'player_rating' => PlayerRating::class,
    ];

    public function index(Request $request): JsonResponse
    {
        $reports = Report::query()
            ->with(['reporter', 'reviewer'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('created_at')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched reports successfully', $reports);
    }

    public function show(Report $report): JsonResponse
    {
        return ApiResponse::success('Fetched report successfully', $report->load(['reporter', 'reviewer', 'reportable']));
    }

    public function store(StoreReportRequest $request): JsonResponse
    {
        $modelClass = self::MODEL_ALIASES[$request->validated('reportable_type')] ?? $request->validated('reportable_type');

        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class) || ! $modelClass::query()->whereKey($request->validated('reportable_id'))->exists()) {
            throw ValidationException::withMessages([
                'reportable_id' => ['Reported record was not found.'],
            ]);
        }

        $report = Report::updateOrCreate(
            [
                'reporter_id' => $request->user()->id,
                'reportable_type' => $modelClass,
                'reportable_id' => $request->validated('reportable_id'),
            ],
            [
                'reason' => $request->validated('reason'),
                'description' => $request->validated('description'),
                'status' => 'pending',
            ]
        );

        return ApiResponse::success('Report submitted successfully', $report->load('reporter'), 201);
    }

    public function review(Request $request, Report $report): JsonResponse
    {
        $report->update([
            'status' => 'reviewing',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return ApiResponse::success('Report marked as reviewing successfully', $report->fresh('reviewer'));
    }

    public function resolve(ReviewReportRequest $request, Report $report): JsonResponse
    {
        $report->update([
            'status' => 'resolved',
            'action_taken' => $request->validated('action_taken'),
            'action_note' => $request->validated('action_note'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return ApiResponse::success('Report resolved successfully', $report->fresh('reviewer'));
    }

    public function dismiss(ReviewReportRequest $request, Report $report): JsonResponse
    {
        $report->update([
            'status' => 'dismissed',
            'action_taken' => $request->validated('action_taken'),
            'action_note' => $request->validated('action_note'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return ApiResponse::success('Report dismissed successfully', $report->fresh('reviewer'));
    }
}
