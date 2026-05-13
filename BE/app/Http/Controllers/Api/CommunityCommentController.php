<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\CommunityPostComment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommunityCommentController extends Controller
{
    public function destroy(Request $request, CommunityPostComment $comment): JsonResponse
    {
        abort_unless(
            $comment->user_id === $request->user()->id || $this->canModerate($request->user()),
            403,
            'You cannot delete this community comment.'
        );

        DB::transaction(function () use ($comment) {
            $post = CommunityPost::query()->whereKey($comment->post_id)->lockForUpdate()->first();
            $deletedCount = count($this->commentTreeIds($comment));
            $comment->delete();

            if ($post) {
                $post->update([
                    'comment_count' => max(0, $post->comment_count - $deletedCount),
                ]);
            }
        });

        return ApiResponse::success('Community comment deleted successfully');
    }

    private function canModerate($user): bool
    {
        return $user->hasRole('super_admin')
            || $user->hasRole('system_staff')
            || $user->hasPermission('community_post.moderate');
    }

    private function commentTreeIds(CommunityPostComment $comment): array
    {
        $ids = [$comment->id];
        $frontier = [$comment->id];

        while ($frontier !== []) {
            $children = CommunityPostComment::whereIn('parent_id', $frontier)->pluck('id')->all();
            $frontier = array_values(array_diff($children, $ids));
            $ids = array_values(array_unique(array_merge($ids, $frontier)));
        }

        return $ids;
    }
}
