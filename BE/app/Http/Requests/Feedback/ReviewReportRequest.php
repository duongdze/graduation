<?php

namespace App\Http\Requests\Feedback;

use App\Http\Requests\ApiRequest;

class ReviewReportRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'action_taken' => ['nullable', 'string', 'in:warning,content_hidden,content_deleted,user_suspended,user_banned'],
            'action_note' => ['nullable', 'string'],
        ];
    }
}
