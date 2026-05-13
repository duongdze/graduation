<?php

namespace App\Services;

use App\Models\PlayerPost;
use App\Models\PlayerPostParticipant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecruitmentService
{
    private const ACTIVE_PARTICIPATION_STATUSES = ['pending', 'approved'];

    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function assertUserHasNoScheduleConflict(
        User $user,
        string $playDate,
        string $startTime,
        string $endTime,
        ?string $excludePostId = null
    ): void {
        $date = Carbon::parse($playDate)->toDateString();
        $normalizedStart = $this->normalizeTime($startTime);
        $normalizedEnd = $this->normalizeTime($endTime);

        $participantConflict = PlayerPostParticipant::query()
            ->join('player_posts', 'player_posts.id', '=', 'player_post_participants.post_id')
            ->where('player_post_participants.user_id', $user->id)
            ->whereIn('player_post_participants.status', self::ACTIVE_PARTICIPATION_STATUSES)
            ->when($excludePostId !== null, fn ($query) => $query->where('player_posts.id', '!=', $excludePostId))
            ->whereDate('player_posts.play_date', $date)
            ->whereNotNull('player_posts.end_time')
            ->whereNotIn('player_posts.status', ['cancelled'])
            ->where('player_posts.start_time', '<', $normalizedEnd)
            ->where('player_posts.end_time', '>', $normalizedStart)
            ->lockForUpdate()
            ->exists();

        $authorConflict = PlayerPost::query()
            ->where('author_id', $user->id)
            ->when($excludePostId !== null, fn ($query) => $query->whereKeyNot($excludePostId))
            ->whereDate('play_date', $date)
            ->whereNotNull('end_time')
            ->whereNotIn('status', ['cancelled'])
            ->where('start_time', '<', $normalizedEnd)
            ->where('end_time', '>', $normalizedStart)
            ->lockForUpdate()
            ->exists();

        if ($participantConflict || $authorConflict) {
            throw ValidationException::withMessages([
                'schedule' => ['You already joined or own another player post that overlaps this play time.'],
            ]);
        }
    }

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

            $this->assertNoScheduleConflict($lockedPost, $user);

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

            $this->notifyParticipantJoined($lockedPost, $participant);

            if ($status === 'approved') {
                $this->notifyParticipantApproved($lockedPost, $participant);
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

            $user = User::query()->whereKey($lockedParticipant->user_id)->firstOrFail();
            $this->assertNoScheduleConflict($lockedPost, $user);

            $lockedParticipant->update([
                'status' => 'approved',
                'responded_at' => now(),
            ]);

            $this->incrementPlayers($lockedPost);
            $this->notifyParticipantApproved($lockedPost, $lockedParticipant);

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
            $this->notifyParticipantRejected($post, $lockedParticipant);

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

    private function assertNoScheduleConflict(PlayerPost $targetPost, User $user): void
    {
        if ($targetPost->end_time === null) {
            throw ValidationException::withMessages([
                'end_time' => ['Player post end time is required to check schedule conflicts.'],
            ]);
        }

        $this->assertUserHasNoScheduleConflict(
            $user,
            Carbon::parse($targetPost->play_date)->toDateString(),
            (string) $targetPost->start_time,
            (string) $targetPost->end_time,
            $targetPost->id
        );
    }

    private function normalizeTime(mixed $value): string
    {
        return Carbon::parse((string) $value)->format('H:i:s');
    }

    private function notifyParticipantJoined(PlayerPost $post, PlayerPostParticipant $participant): void
    {
        if ($post->author_id === $participant->user_id) {
            return;
        }

        $this->notificationService->createForUser(
            $post->author_id,
            'participant_joined',
            'New participant request',
            'A player requested to join your player post.',
            'PlayerPost',
            $post->id,
            ['participant_id' => $participant->id, 'user_id' => $participant->user_id]
        );
    }

    private function notifyParticipantApproved(PlayerPost $post, PlayerPostParticipant $participant): void
    {
        $this->notificationService->createForUser(
            $participant->user_id,
            'participant_approved',
            'Join request approved',
            'Your player post join request was approved.',
            'PlayerPost',
            $post->id,
            ['participant_id' => $participant->id]
        );
    }

    private function notifyParticipantRejected(PlayerPost $post, PlayerPostParticipant $participant): void
    {
        $this->notificationService->createForUser(
            $participant->user_id,
            'participant_rejected',
            'Join request rejected',
            'Your player post join request was rejected.',
            'PlayerPost',
            $post->id,
            ['participant_id' => $participant->id]
        );
    }
}
