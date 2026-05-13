<?php

namespace App\Http\Requests\System;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class UpsertSystemPolicyRequest extends ApiRequest
{
    public function rules(): array
    {
        $policy = $this->route('policy');
        $policyId = is_object($policy) ? $policy->id : $policy;
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'key' => [$required, 'string', 'max:100', Rule::unique('system_policies', 'key')->ignore($policyId)],
            'title' => [$required, 'string', 'max:255'],
            'content' => [$required, 'string'],
            'type' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'effective_from' => ['nullable', 'date'],
        ];
    }
}
