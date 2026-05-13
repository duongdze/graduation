<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\StoreConversationRequest;
use App\Models\Conversation;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $conversations = Conversation::query()
            ->with(['users', 'creator'])
            ->whereHas('participants', fn ($query) => $query->where('user_id', $request->user()->id))
            ->orderByDesc('last_message_at')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched conversations successfully', $conversations);
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        $conversation = DB::transaction(function () use ($request) {
            $conversation = Conversation::create([
                'type' => $request->validated('type') ?? 'direct',
                'reference_type' => $request->validated('reference_type'),
                'reference_id' => $request->validated('reference_id'),
                'title' => $request->validated('title'),
                'created_by' => $request->user()->id,
            ]);

            $participantIds = collect($request->validated('participant_ids'))
                ->push($request->user()->id)
                ->unique()
                ->values();

            foreach ($participantIds as $participantId) {
                $conversation->participants()->create([
                    'user_id' => $participantId,
                    'last_read_at' => $participantId === $request->user()->id ? now() : null,
                ]);
            }

            return $conversation;
        });

        return ApiResponse::success('Conversation created successfully', $conversation->load(['users', 'creator']), 201);
    }
}
