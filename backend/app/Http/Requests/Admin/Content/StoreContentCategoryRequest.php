<?php

namespace App\Http\Requests\Admin\Content;

use App\Http\Requests\Admin\Common\AdminFormRequest;
use App\Models\ContentArticle;
use Illuminate\Validation\Rule;

class StoreContentCategoryRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'content_type' => ['required_without:type', Rule::in([ContentArticle::TYPE_NOTICE, ContentArticle::TYPE_HELP])],
            'type' => ['required_without:content_type', Rule::in([ContentArticle::TYPE_NOTICE, ContentArticle::TYPE_HELP])],
            'name' => ['required', 'string', 'max:80'],
            'slug' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('content_categories', 'slug')->where(
                    fn ($query) => $query->where('content_type', (string) ($this->input('content_type') ?: $this->input('type')))
                ),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'integer', Rule::in([0, 1])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('content_type') && $this->filled('type')) {
            $this->merge(['content_type' => $this->input('type')]);
        }

        if (! $this->filled('type') && $this->filled('content_type')) {
            $this->merge(['type' => $this->input('content_type')]);
        }
    }
}
