<?php

namespace App\Http\Requests\Venue;

use App\Http\Requests\ApiRequest;

class UpsertVenueCourtRequest extends ApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'cluster_id' => [$required, 'uuid', 'exists:venue_clusters,id'],
            'court_type_id' => [$required, 'integer', 'exists:court_types,id'],
            'name' => [$required, 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:active,maintenance'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
