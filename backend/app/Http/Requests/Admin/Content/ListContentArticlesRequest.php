<?php

namespace App\Http\Requests\Admin\Content;

use App\Http\Requests\Admin\Common\AdminFormRequest;
use App\Models\ContentArticle;
use Illuminate\Validation\Rule;

class ListContentArticlesRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'content_type' => ['nullable', Rule::in([ContentArticle::TYPE_NOTICE, ContentArticle::TYPE_HELP])],
            'type' => ['nullable', Rule::in([ContentArticle::TYPE_NOTICE, ContentArticle::TYPE_HELP])],
            'status' => ['nullable', 'integer', Rule::in([
                ContentArticle::STATUS_DRAFT,
                ContentArticle::STATUS_PUBLISHED,
                ContentArticle::STATUS_OFFLINE,
            ])],
            'keyword' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', Rule::exists('content_categories', 'id')],
            'content_category_id' => ['nullable', 'integer', Rule::exists('content_categories', 'id')],
            'is_pinned' => ['nullable', 'integer', Rule::in([0, 1])],
        ]);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('content_type') && $this->filled('type')) {
            $this->merge(['content_type' => $this->input('type')]);
        }

        if (! $this->filled('type') && $this->filled('content_type')) {
            $this->merge(['type' => $this->input('content_type')]);
        }

        if (! $this->filled('category_id') && $this->filled('content_category_id')) {
            $this->merge(['category_id' => $this->input('content_category_id')]);
        }

        if (! $this->filled('content_category_id') && $this->filled('category_id')) {
            $this->merge(['content_category_id' => $this->input('category_id')]);
        }
    }
}
