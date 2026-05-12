<?php

namespace App\Http\Requests\Users;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends ApiRequest
{
    public function rules(): array
    {
        $user = $this->route('user');
        $userId = is_object($user) ? $user->id : $user;

        return [
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:15', Rule::unique('users', 'phone')->ignore($userId)],
            'password' => ['nullable', Password::min(8)],
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
