<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\ApiRequest;

class StoreMessageRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'string'],
        ];
    }
}
