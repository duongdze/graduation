<?php

namespace App\Http\Requests\System;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class UpsertSystemPostRequest extends ApiRequest
{
    public function rules(): array
    {
        $post = $this->route('post');
        $postId = is_object($post) ? $post->id : $post;
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:255'],
            'slug' => [$required, 'string', 'max:255', Rule::unique('system_posts', 'slug')->ignore($postId)],
            'content' => [$required, 'string'],
            'thumbnail' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', 'in:draft,published,hidden'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
