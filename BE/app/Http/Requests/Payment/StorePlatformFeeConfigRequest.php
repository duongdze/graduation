<?php

namespace App\Http\Requests\Payment;

use App\Http\Requests\ApiRequest;

class StorePlatformFeeConfigRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'fee_percent' => ['required', 'numeric', 'between:0,100'],
            'max_fee_percent' => ['required', 'numeric', 'between:0,100'],
            'effective_from' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ((float) $this->input('fee_percent') > (float) $this->input('max_fee_percent')) {
                $validator->errors()->add('fee_percent', 'The fee percent must be less than or equal to max fee percent.');
            }
        });
    }
}
