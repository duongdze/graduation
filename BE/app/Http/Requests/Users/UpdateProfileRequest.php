<?php

namespace App\Http\Requests\Users;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()?->id)],
            'phone' => ['nullable', 'string', 'max:15', Rule::unique('users', 'phone')->ignore($this->user()?->id)],
            'avatar_url' => ['nullable', 'string', 'max:500'],
            'bio' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'ward' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'preferred_sports' => ['nullable', 'array'],
            'preferred_sports.*' => ['string', 'max:50'],
            'preferred_position' => ['nullable', 'string', 'max:50'],
        ];
    }
}
