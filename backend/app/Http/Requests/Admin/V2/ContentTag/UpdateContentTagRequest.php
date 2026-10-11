<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\ContentTag;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use App\Models\ContentTag;
use Illuminate\Validation\Rule;

class UpdateContentTagRequest extends AdminFormRequest
{
    public function rules(): array
    {
        $routeTag = $this->route('tag');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('content_tags', 'name')->ignore(
                    $routeTag instanceof ContentTag ? (int) $routeTag->id : null,
                ),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }
}
