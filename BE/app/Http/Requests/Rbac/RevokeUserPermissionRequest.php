<?php

namespace App\Http\Requests\Rbac;

use App\Http\Requests\ApiRequest;

class RevokeUserPermissionRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'permission_id' => ['required_without:permission_code', 'integer', 'exists:permissions,id'],
            'permission_code' => ['required_without:permission_id', 'string', 'exists:permissions,code'],
            'scope_type' => ['nullable', 'string', 'in:system,venue'],
            'scope_id' => ['nullable', 'uuid'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
