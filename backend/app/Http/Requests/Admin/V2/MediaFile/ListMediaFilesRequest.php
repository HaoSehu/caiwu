<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\MediaFile;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use Illuminate\Validation\Rule;

class ListMediaFilesRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            ...$this->paginationRules(presence: 'sometimes'),
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
            'group' => ['sometimes', 'string', 'max:50', 'alpha_dash:ascii'],
            'keyword' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', Rule::in(['image', 'video'])],
        ];
    }
}
