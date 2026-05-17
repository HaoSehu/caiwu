<?php

namespace App\Http\Requests\Admin\Content;

use App\Http\Requests\Admin\Common\AdminFormRequest;
use App\Models\ContentArticle;
use Illuminate\Validation\Rule;

class ListContentCategoriesRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'content_type' => ['required_without:type', Rule::in([ContentArticle::TYPE_NOTICE, ContentArticle::TYPE_HELP])],
            'type' => ['required_without:content_type', Rule::in([ContentArticle::TYPE_NOTICE, ContentArticle::TYPE_HELP])],
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
