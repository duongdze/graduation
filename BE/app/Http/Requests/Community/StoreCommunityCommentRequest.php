<?php

namespace App\Http\Requests\Community;

use App\Http\Requests\ApiRequest;

class StoreCommunityCommentRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'uuid', 'exists:community_post_comments,id'],
        ];
    }
}
