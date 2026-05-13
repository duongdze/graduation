<?php

namespace App\Http\Requests\Recruitment;

use App\Http\Requests\ApiRequest;

class UpsertPlayerPostRequest extends ApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sport_type' => [$required, 'string', 'max:50'],
            'court_type_id' => ['nullable', 'integer', 'exists:court_types,id'],
            'venue_cluster_id' => ['nullable', 'uuid', 'exists:venue_clusters,id'],
            'booking_id' => ['nullable', 'uuid', 'exists:bookings,id'],
            'play_date' => [$required, 'date'],
            'start_time' => [$required, 'date_format:H:i'],
            'end_time' => [$required, 'date_format:H:i', 'after:start_time'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'needed_players' => [$required, 'integer', 'min:1'],
            'max_players' => [$required, 'integer', 'gte:needed_players'],
            'skill_level' => ['nullable', 'string', 'in:beginner,intermediate,advanced,any'],
            'gender_preference' => ['nullable', 'string', 'in:male,female,any'],
            'cost_per_player' => ['nullable', 'numeric', 'min:0'],
            'is_auto_approve' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'in:open,full,closed,cancelled'],
        ];
    }
}
