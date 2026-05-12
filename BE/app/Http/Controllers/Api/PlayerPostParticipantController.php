<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\JoinPlayerPostRequest;
use App\Models\PlayerPost;
use App\Models\PlayerPostParticipant;
use App\Services\RecruitmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerPostParticipantController extends Controller
{
    public function __construct(private readonly RecruitmentService $recruitmentService) {}

    public function join(JoinPlayerPostRequest $request, PlayerPost $playerPost): JsonResponse
    {
        $participant = $this->recruitmentService->join($playerPost, $request->user(), $request->validated('message'));

        return ApiResponse::success('Joined player post successfully', $participant, 201);
    }

    public function approve(Request $request, PlayerPost $playerPost, PlayerPostParticipant $participant): JsonResponse
    {
        $this->assertCanManagePost($request, $playerPost);

        $participant = $this->recruitmentService->approve($playerPost, $participant);

        return ApiResponse::success('Participant approved successfully', $participant);
    }

    public function reject(Request $request, PlayerPost $playerPost, PlayerPostParticipant $participant): JsonResponse
    {
        $this->assertCanManagePost($request, $playerPost);

        $participant = $this->recruitmentService->reject($playerPost, $participant);

        return ApiResponse::success('Participant rejected successfully', $participant);
    }

    public function leave(Request $request, PlayerPost $playerPost): JsonResponse
    {
        $this->recruitmentService->leave($playerPost, $request->user());

        return ApiResponse::success('Left player post successfully');
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
