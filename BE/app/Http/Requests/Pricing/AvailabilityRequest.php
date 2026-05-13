<?php

namespace App\Http\Requests\Pricing;

use App\Http\Requests\ApiRequest;

class AvailabilityRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:30', 'max:480'],
        ];
    }
}
