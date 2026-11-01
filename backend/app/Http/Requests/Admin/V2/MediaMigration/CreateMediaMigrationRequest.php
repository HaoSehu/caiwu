<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\MediaMigration;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use Illuminate\Validation\Rule;

class CreateMediaMigrationRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(['to_remote', 'to_local'])],
            'driver' => ['nullable', 'required_if:direction,to_remote', 'string', 'max:24', 'alpha_dash:ascii'],
            'scope' => ['required', Rule::in(['all', 'ids', 'filter'])],
            'ids' => ['required_if:scope,ids', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'filter.group' => ['nullable', 'string', 'max:50', 'alpha_dash:ascii'],
            'filter.keyword' => ['nullable', 'string', 'max:100'],
            'filter.type' => ['nullable', Rule::in(['image', 'video'])],
            'delete_source' => ['nullable', 'boolean'],
        ];
    }
}
