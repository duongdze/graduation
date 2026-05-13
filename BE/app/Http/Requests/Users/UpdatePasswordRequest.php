<?php

namespace App\Http\Requests\Users;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
