<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\ContentTag;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class ListContentTagsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->safe()->only(['keyword']);
    }
}
