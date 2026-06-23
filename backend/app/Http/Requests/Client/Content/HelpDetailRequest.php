<?php

namespace App\Http\Requests\Client\Content;

use App\Http\Requests\Client\Common\ClientFormRequest;
use Illuminate\Validation\Rule;

class HelpDetailRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', Rule::exists('content_categories', 'id')],
            'content_category_id' => ['nullable', 'integer', Rule::exists('content_categories', 'id')],
            'is_recommended' => ['nullable', 'integer', Rule::in([0, 1])],
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
