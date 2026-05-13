<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\StoreMessageRequest;
use App\Models\Conversation;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $this->assertParticipant($request, $conversation);

        $messages = $conversation->messages()
            ->with('sender')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 30));

        return ApiResponse::paginated('Fetched messages successfully', $messages);
    }

    public function store(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $this->assertParticipant($request, $conversation);

        $message = DB::transaction(function () use ($request, $conversation) {
            $message = $conversation->messages()->create([
                'sender_id' => $request->user()->id,
                'content' => $request->validated('content'),
                'is_system' => false,
            ]);

            $conversation->update(['last_message_at' => now()]);

            return $message;
        });

        return ApiResponse::success('Message sent successfully', $message->load('sender'), 201);
    }

    public function read(Request $request, Conversation $conversation): JsonResponse
    {
        $this->assertParticipant($request, $conversation);

        $conversation->participants()
            ->where('user_id', $request->user()->id)
            ->update(['last_read_at' => now()]);

        return ApiResponse::success('Conversation marked as read successfully');
    }

    private function assertParticipant(Request $request, Conversation $conversation): void
    {
        abort_unless(
            $conversation->participants()->where('user_id', $request->user()->id)->exists(),
            403,
            'You are not a participant in this conversation.'
        );
    }
}
