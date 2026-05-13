<?php

namespace App\Http\Requests\System;

use App\Http\Requests\ApiRequest;

class UpsertBannerRequest extends ApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:255'],
            'image_url' => [$required, 'string', 'max:1000'],
            'link_url' => ['nullable', 'string', 'max:1000'],
            'position' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
