<?php

namespace App\Http\Requests\Payment;

use App\Http\Requests\ApiRequest;

class StoreRefundRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'booking_id' => ['required', 'uuid', 'exists:bookings,id'],
            'payment_id' => ['required', 'uuid', 'exists:payments,id'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string'],
        ];
    }
}
