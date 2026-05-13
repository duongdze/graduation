<?php

namespace App\Http\Requests\Rbac;

use App\Http\Requests\ApiRequest;

class SyncRolePermissionsRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'permission_ids' => ['required_without:permission_codes', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
            'permission_codes' => ['required_without:permission_ids', 'array'],
            'permission_codes.*' => ['string', 'exists:permissions,code'],
        ];
    }
}
