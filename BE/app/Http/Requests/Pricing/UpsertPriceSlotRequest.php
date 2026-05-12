<?php

namespace App\Http\Requests\Pricing;

use App\Http\Requests\ApiRequest;

class UpsertPriceSlotRequest extends ApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'cluster_id' => [$required, 'uuid', 'exists:venue_clusters,id'],
            'start_time' => [$required, 'date_format:H:i'],
            'end_time' => [$required, 'date_format:H:i', 'after:start_time'],
            'price' => [$required, 'numeric', 'min:0'],
            'apply_to_days' => ['nullable', 'array'],
            'apply_to_days.*' => ['integer', 'between:0,6'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
