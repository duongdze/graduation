<?php

namespace App\Http\Requests\Feedback;

use App\Http\Requests\ApiRequest;

class ResolveComplaintRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'resolve_note' => ['nullable', 'string'],
        ];
    }
}
