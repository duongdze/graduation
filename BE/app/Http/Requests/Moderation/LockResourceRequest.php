<?php

namespace App\Http\Requests\Moderation;

use App\Http\Requests\ApiRequest;

class LockResourceRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
