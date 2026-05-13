<?php

namespace App\Http\Requests\Feedback;

use App\Http\Requests\ApiRequest;

class StoreReportRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'reportable_type' => ['required', 'string', 'max:50'],
            'reportable_id' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'in:spam,offensive,fake,harassment,other'],
            'description' => ['nullable', 'string'],
        ];
    }
}
