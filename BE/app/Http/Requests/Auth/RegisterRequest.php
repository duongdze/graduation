<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:15', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'bio' => ['nullable', 'string'],
            'preferred_sports' => ['nullable', 'array'],
            'preferred_sports.*' => ['string', 'max:50'],
            'preferred_position' => ['nullable', 'string', 'max:50'],
        ];
    }
}
