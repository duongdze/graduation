<?php

namespace App\Http\Requests\Feedback;

use App\Http\Requests\ApiRequest;

class UpsertReviewRequest extends ApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'booking_id' => [$required, 'uuid', 'exists:bookings,id'],
            'rating' => [$required, 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string'],
            'reply_content' => ['nullable', 'string'],
            'is_visible' => ['nullable', 'boolean'],
        ];
    }
}
