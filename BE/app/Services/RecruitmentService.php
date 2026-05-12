<?php

namespace App\Services;

use App\Models\PlayerPost;
use App\Models\PlayerPostParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecruitmentService
{
    public function join(PlayerPost $post, User $user, ?string $message = null): PlayerPostParticipant
    {
        return DB::transaction(function () use ($post, $user, $message) {
            $lockedPost = PlayerPost::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();

            if ($lockedPost->author_id === $user->id) {
                throw ValidationException::withMessages([
                    'user_id' => ['Post author cannot join their own post.'],
                ]);
            }

            if ($lockedPost->status !== 'open') {
                throw ValidationException::withMessages([
                    'status' => ['This post is not open for joining.'],
                ]);
            }

            if ($lockedPost->current_players >= $this->capacityLimit($lockedPost)) {
                throw ValidationException::withMessages([
                    'current_players' => ['This post is already full.'],
                ]);
            }

            $existing = PlayerPostParticipant::where('post_id', $lockedPost->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->status !== 'cancelled') {
                throw ValidationException::withMessages([
                    'user_id' => ['You already joined or requested to join this post.'],
                ]);
            }

            $status = $lockedPost->is_auto_approve ? 'approved' : 'pending';
            $participant = PlayerPostParticipant::updateOrCreate(
                [
                    'post_id' => $lockedPost->id,
                    'user_id' => $user->id,
                ],
                [
                    'status' => $status,
                    'message' => $message,
                    'responded_at' => $status === 'approved' ? now() : null,
                ]
            );

            if ($status === 'approved') {
                $this->incrementPlayers($lockedPost);
            }

            return $participant->fresh(['post', 'user']);
        });
    }

    public function approve(PlayerPost $post, PlayerPostParticipant $participant): PlayerPostParticipant
    {
        return DB::transaction(function () use ($post, $participant) {
            $lockedPost = PlayerPost::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();
            $lockedParticipant = PlayerPostParticipant::where('post_id', $lockedPost->id)
                ->whereKey($participant->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedParticipant->status === 'approved') {
                return $lockedParticipant->load(['post', 'user']);
            }

            if ($lockedPost->current_players >= $this->capacityLimit($lockedPost)) {
                throw ValidationException::withMessages([
                    'current_players' => ['This post is already full.'],
                ]);
            }

            $lockedParticipant->update([
                'status' => 'approved',
                'responded_at' => now(),
            ]);

            $this->incrementPlayers($lockedPost);

            return $lockedParticipant->fresh(['post', 'user']);
        });
    }

    public function reject(PlayerPost $post, PlayerPostParticipant $participant): PlayerPostParticipant
    {
        return DB::transaction(function () use ($post, $participant) {
            $lockedParticipant = PlayerPostParticipant::where('post_id', $post->id)
                ->whereKey($participant->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedParticipant->status === 'approved') {
                throw ValidationException::withMessages([
                    'status' => ['Approved participants must leave instead of being rejected.'],
                ]);
            }

            $lockedParticipant->update([
                'status' => 'rejected',
                'responded_at' => now(),
            ]);

            return $lockedParticipant->fresh(['post', 'user']);
        });
    }

    public function leave(PlayerPost $post, User $user): void
    {
        DB::transaction(function () use ($post, $user) {
            $lockedPost = PlayerPost::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();
            $participant = PlayerPostParticipant::where('post_id', $lockedPost->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($participant->status === 'approved') {
                $lockedPost->update([
                    'current_players' => max(1, $lockedPost->current_players - 1),
                    'status' => 'open',
                ]);
            }

            $participant->update([
                'status' => 'cancelled',
                'responded_at' => now(),
            ]);
        });
    }

    private function incrementPlayers(PlayerPost $post): void
    {
        $newCount = $post->current_players + 1;
        $post->update([
            'current_players' => $newCount,
            'status' => $newCount >= $this->capacityLimit($post) ? 'full' : 'open',
        ]);
    }

    private function capacityLimit(PlayerPost $post): int
    {
        return min((int) $post->max_players, (int) $post->needed_players + 1);
    }
}
