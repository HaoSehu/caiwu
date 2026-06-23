<?php

namespace App\Http\Requests\Admin\MediaFile;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class StoreRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
            'group' => ['nullable', 'string', 'max:50', 'alpha_dash:ascii'],
        ];
    }
}
