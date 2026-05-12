<?php

namespace App\Http\Requests\Recruitment;

use App\Http\Requests\ApiRequest;

class JoinPlayerPostRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
