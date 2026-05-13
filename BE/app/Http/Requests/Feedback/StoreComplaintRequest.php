<?php

namespace App\Http\Requests\Feedback;

use App\Http\Requests\ApiRequest;

class StoreComplaintRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'booking_id' => ['required', 'uuid', 'exists:bookings,id'],
            'content' => ['required', 'string'],
        ];
    }
}
