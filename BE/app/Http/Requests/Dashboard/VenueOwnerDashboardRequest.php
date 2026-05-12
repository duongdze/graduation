<?php

namespace App\Http\Requests\Dashboard;

use App\Http\Requests\ApiRequest;

class VenueOwnerDashboardRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'cluster_id' => ['sometimes', 'uuid', 'exists:venue_clusters,id'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }
}
