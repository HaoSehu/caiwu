<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\MemberLevel;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class CreateMemberLevelRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'status' => ['nullable', 'integer', 'in:0,1'],
            'remark' => ['nullable', 'string', 'max:255'],
            'per_page' => ['prohibited'],
            'pageSize' => ['prohibited'],
        ];
    }

    public function payload(): array
    {
        return $this->safe()->only([
            'name',
            'status',
            'remark',
        ]);
    }
}
