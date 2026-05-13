<?php

namespace App\Http\Requests\Rbac;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends ApiRequest
{
    public function rules(): array
    {
        $role = $this->route('role');
        $roleId = is_object($role) ? $role->id : $role;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:50', 'alpha_dash', Rule::unique('roles', 'name')->ignore($roleId)],
            'display_name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_system' => ['nullable', 'boolean'],
        ];
    }
}
