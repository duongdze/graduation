<?php

namespace App\Http\Requests\Media;

use App\Http\Requests\ApiRequest;

class StoreMediaRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'mediable_type' => ['required', 'string', 'max:50'],
            'mediable_id' => ['required', 'uuid'],
            'collection' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'file' => ['required', 'image', 'max:5120'],
        ];
    }
}
