<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\ContentCategory;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use App\Models\ContentArticle;
use Illuminate\Validation\Rule;

class ListContentCategoriesRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            ...$this->paginationRules(presence: 'sometimes'),
            'content_type' => ['required', Rule::in([ContentArticle::TYPE_NOTICE, ContentArticle::TYPE_HELP])],
            'type' => ['prohibited'],
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
        ];
    }

    public function contentType(): string
    {
        return (string) $this->validated('content_type');
    }
}
