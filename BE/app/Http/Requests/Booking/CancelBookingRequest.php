<?php

namespace App\Http\Requests\Booking;

use App\Http\Requests\ApiRequest;

class CancelBookingRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'cancel_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
