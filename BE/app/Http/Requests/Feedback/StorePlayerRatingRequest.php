<?php

namespace App\Http\Requests\Feedback;

use App\Http\Requests\ApiRequest;

class StorePlayerRatingRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'rated_user_id' => ['required', 'uuid', 'exists:users,id', 'different:rater_id'],
            'post_id' => ['nullable', 'uuid', 'exists:player_posts,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}
