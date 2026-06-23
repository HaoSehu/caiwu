<?php

namespace App\Http\Requests\Admin\Setting;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class IndexSettingRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'group' => ['nullable', 'string', 'max:50'],
        ];
    }
}
