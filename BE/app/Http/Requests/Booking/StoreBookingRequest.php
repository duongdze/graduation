<?php

namespace App\Http\Requests\Booking;

use App\Http\Requests\ApiRequest;

class StoreBookingRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'uuid', 'exists:users,id'],
            'court_id' => ['required', 'uuid', 'exists:venue_courts,id'],
            'booking_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'source' => ['nullable', 'string', 'in:online,counter'],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'total_price' => ['nullable', 'numeric', 'min:0'],
            'walk_in_name' => ['nullable', 'string', 'max:255'],
            'walk_in_phone' => ['nullable', 'string', 'max:15'],
            'note' => ['nullable', 'string'],
        ];
    }
}
