<?php

namespace App\Http\Requests\Venue;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class UpsertCourtTypeRequest extends ApiRequest
{
    public function rules(): array
    {
        $courtType = $this->route('courtType');
        $courtTypeId = is_object($courtType) ? $courtType->id : $courtType;
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:100', Rule::unique('court_types', 'name')->ignore($courtTypeId)],
            'description' => ['nullable', 'string'],
            'player_count' => [$required, 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
