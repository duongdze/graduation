<?php

namespace App\Http\Requests\Rbac;

use App\Http\Requests\ApiRequest;

class SyncUserRolesRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*.role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'roles.*.name' => ['nullable', 'string', 'exists:roles,name'],
            'roles.*.scope_type' => ['nullable', 'string', 'in:system,venue'],
            'roles.*.scope_id' => ['nullable', 'uuid'],
        ];
    }
}
