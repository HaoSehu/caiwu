<?php

namespace App\Http\Requests\Admin\Content;

use App\Http\Requests\Admin\Common\AdminFormRequest;
use App\Models\ContentArticle;
use Illuminate\Validation\Rule;

class StoreContentArticleRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'content_type' => ['required_without:type', Rule::in([ContentArticle::TYPE_NOTICE, ContentArticle::TYPE_HELP])],
            'type' => ['required_without:content_type', Rule::in([ContentArticle::TYPE_NOTICE, ContentArticle::TYPE_HELP])],
            'category_id' => ['required_without:content_category_id', 'integer', Rule::exists('content_categories', 'id')],
            'content_category_id' => ['required_without:category_id', 'integer', Rule::exists('content_categories', 'id')],
            'title' => ['required', 'string', 'max:200'],
            'slug' => [
                'nullable',
                'string',
                'max:220',
                Rule::unique('content_articles', 'slug')->where(
                    fn ($query) => $query->where('content_type', (string) ($this->input('content_type') ?: $this->input('type')))
                ),
            ],
            'summary' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'keywords' => ['nullable', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'integer', Rule::in([
                ContentArticle::STATUS_DRAFT,
                ContentArticle::STATUS_PUBLISHED,
                ContentArticle::STATUS_OFFLINE,
            ])],
            'is_pinned' => ['nullable', 'integer', Rule::in([0, 1])],
            'cover_image' => ['nullable', 'required_if:is_pinned,1', 'string', 'max:500'],
            'is_recommended' => ['nullable', 'integer', Rule::in([0, 1])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'publish_at' => ['nullable', 'date'],
            'remark' => ['nullable', 'string', 'max:255'],
            'operator' => ['nullable', 'string', 'max:50'],
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

        if (! $this->filled('category_id') && $this->filled('content_category_id')) {
            $this->merge(['category_id' => $this->input('content_category_id')]);
        }

        if (! $this->filled('content_category_id') && $this->filled('category_id')) {
            $this->merge(['content_category_id' => $this->input('category_id')]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ((int) $this->input('is_pinned') === 1 && (int) $this->input('is_recommended') === 1) {
                $validator->errors()->add('is_recommended', '置顶和推荐不能同时选择');
            }
        });
    }
}
