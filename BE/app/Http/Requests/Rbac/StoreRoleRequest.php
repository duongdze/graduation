<?php

namespace App\Http\Requests\Rbac;

use App\Http\Requests\ApiRequest;

class StoreRoleRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:roles,name'],
            'display_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_system' => ['nullable', 'boolean'],
        ];
    }
}
