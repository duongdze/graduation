<?php

namespace App\Http\Requests\Pricing;

use App\Http\Requests\ApiRequest;

class UpsertHolidayPriceRequest extends ApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'cluster_id' => [$required, 'uuid', 'exists:venue_clusters,id'],
            'holiday_date' => [$required, 'date'],
            'price' => [$required, 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
