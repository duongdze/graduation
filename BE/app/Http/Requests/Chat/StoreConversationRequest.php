<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\ApiRequest;

class StoreConversationRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', 'in:direct,post'],
            'reference_type' => ['nullable', 'string', 'max:50'],
            'reference_id' => ['nullable', 'uuid'],
            'title' => ['nullable', 'string', 'max:255'],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['uuid', 'exists:users,id'],
        ];
    }
}
