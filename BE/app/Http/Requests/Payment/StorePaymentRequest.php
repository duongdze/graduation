<?php

namespace App\Http\Requests\Payment;

use App\Http\Requests\ApiRequest;

class StorePaymentRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'booking_id' => ['required', 'uuid', 'exists:bookings,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'in:vnpay,momo,cash'],
            'gateway_txn_id' => ['nullable', 'string', 'max:255', 'unique:payments,gateway_txn_id'],
            'gateway_response' => ['nullable', 'array'],
        ];
    }
}
