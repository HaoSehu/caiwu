<?php

namespace App\Http\Requests\Admin\MediaFile;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class StoreRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm,video/ogg,video/quicktime,video/x-m4v', 'max:102400'],
            'group' => ['nullable', 'string', 'max:50', 'alpha_dash:ascii'],
        ];
    }
}
