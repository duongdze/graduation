<?php

namespace App\Http\Requests\Rbac;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'string', 'max:100', Rule::unique('permissions', 'code')->ignore($this->route('permission'))],
            'name' => ['sometimes', 'string', 'max:255'],
            'group_name' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
