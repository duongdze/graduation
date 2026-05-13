<?php

namespace App\Http\Requests\Community;

use App\Http\Requests\ApiRequest;

class UpsertCommunityPostRequest extends ApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'venue_cluster_id' => ['nullable', 'uuid', 'exists:venue_clusters,id'],
            'content' => [$required, 'string'],
            'status' => ['nullable', 'string', 'in:published,hidden'],
        ];
    }
}
