<?php

namespace App\Http\Requests\Pricing;

use App\Http\Requests\ApiRequest;

class UpdateBookingConfigRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'min_duration_minutes' => ['required', 'integer', 'min:30'],
            'max_duration_minutes' => ['required', 'integer', 'max:480', 'gt:min_duration_minutes'],
            'cancel_before_hours' => ['required', 'integer', 'min:0'],
            'refund_percent' => ['required', 'integer', 'between:0,100'],
        ];
    }
}
