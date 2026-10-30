<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\MediaFile;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use App\Support\Pagination;
use Illuminate\Validation\Rule;

class ListMediaFilesRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'page_size' => ['sometimes', 'integer', 'min:1', 'max:'.Pagination::MAX_PAGE_SIZE],
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
            'group' => ['sometimes', 'string', 'max:50', 'alpha_dash:ascii'],
            'keyword' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', Rule::in(['image', 'video'])],
        ];
    }
}
