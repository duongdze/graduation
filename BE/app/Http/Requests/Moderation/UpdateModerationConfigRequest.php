<?php

namespace App\Http\Requests\Moderation;

use App\Http\Requests\ApiRequest;

class UpdateModerationConfigRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'value' => ['required'],
            'value_type' => ['nullable', 'string', 'in:integer,decimal,string,boolean'],
            'description' => ['nullable', 'string'],
        ];
    }
}
