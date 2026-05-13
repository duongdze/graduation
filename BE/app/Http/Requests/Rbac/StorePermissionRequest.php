<?php

namespace App\Http\Requests\Rbac;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100', Rule::unique('permissions', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'group_name' => ['required', 'string', 'max:100'],
        ];
    }
}
