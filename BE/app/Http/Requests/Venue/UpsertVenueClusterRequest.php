<?php

namespace App\Http\Requests\Venue;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class UpsertVenueClusterRequest extends ApiRequest
{
    public function rules(): array
    {
        $cluster = $this->route('venueCluster');
        $clusterId = is_object($cluster) ? $cluster->id : $cluster;
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'owner_id' => [$required, 'uuid', 'exists:users,id'],
            'name' => [$required, 'string', 'max:255'],
            'slug' => [$required, 'string', 'max:255', Rule::unique('venue_clusters', 'slug')->ignore($clusterId)],
            'description' => ['nullable', 'string'],
            'phone_contact' => ['nullable', 'string', 'max:15'],
            'address' => [$required, 'string'],
            'ward' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string', 'max:50'],
            'status' => ['nullable', 'string', 'in:pending,active,rejected,locked'],
        ];
    }
}
