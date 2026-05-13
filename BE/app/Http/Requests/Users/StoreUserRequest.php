<?php

namespace App\Http\Requests\Users;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:15', 'unique:users,phone'],
            'password' => ['required', Password::min(8)],
            'status' => ['nullable', 'string', 'in:pending_verify,active,locked'],
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
